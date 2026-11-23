<?php
/**
 * Lectura de Excel (.xlsx) y CSV sin librerías + aplicación de la importación de alumnos/membresías.
 */
declare(strict_types=1);

function sheet_read(string $path, string $ext): array
{
    return $ext === 'xlsx' ? xlsx_read($path) : csv_read($path);
}

function csv_read(string $path): array
{
    $raw = file_get_contents($path);
    if (str_starts_with($raw, "\xEF\xBB\xBF")) $raw = substr($raw, 3);
    if (!mb_check_encoding($raw, 'UTF-8')) $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
    $first = strtok($raw, "\n");
    $sep = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
    $rows = [];
    $fh = fopen('php://memory', 'r+');
    fwrite($fh, $raw);
    rewind($fh);
    while (($r = fgetcsv($fh, 0, $sep, '"', '\\')) !== false) {
        if (count(array_filter($r, fn($c) => trim((string) $c) !== '')) === 0) continue;
        $rows[] = array_map(fn($c) => trim((string) $c), $r);
    }
    fclose($fh);
    return $rows;
}

function xlsx_read(string $path): array
{
    if (!class_exists('ZipArchive')) throw new RuntimeException('El servidor no tiene ZipArchive. Sube el archivo como CSV.');
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('No se pudo abrir el Excel.');
    $shared = [];
    if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
        $sx = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
        foreach ($sx->si as $si) {
            if (isset($si->t)) $shared[] = (string) $si->t;
            else {
                $t = '';
                foreach ($si->r as $r) $t .= (string) $r->t;
                $shared[] = $t;
            }
        }
    }
    // Primera hoja según workbook
    $sheetFile = 'xl/worksheets/sheet1.xml';
    $wb = $zip->getFromName('xl/workbook.xml');
    $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($wb && $rels) {
        $w = simplexml_load_string($wb, 'SimpleXMLElement', LIBXML_NONET);
        $w->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $first = $w->xpath('//m:sheets/m:sheet')[0] ?? null;
        if ($first) {
            $rid = (string) $first->attributes('r', true)->id;
            $rx = simplexml_load_string($rels, 'SimpleXMLElement', LIBXML_NONET);
            foreach ($rx->Relationship as $rel) {
                if ((string) $rel['Id'] === $rid) {
                    $t = ltrim((string) $rel['Target'], '/');
                    $sheetFile = str_starts_with($t, 'xl/') ? $t : 'xl/' . $t;
                }
            }
        }
    }
    $sheetXml = $zip->getFromName($sheetFile);
    $styles = $zip->getFromName('xl/styles.xml');
    $zip->close();
    if ($sheetXml === false) throw new RuntimeException('El Excel no tiene hojas legibles.');

    // Formatos de fecha (numFmtId de estilos)
    $dateStyles = [];
    if ($styles) {
        $st = simplexml_load_string($styles, 'SimpleXMLElement', LIBXML_NONET);
        $customDate = [];
        if (isset($st->numFmts)) foreach ($st->numFmts->numFmt as $nf) {
            $code = strtolower((string) $nf['formatCode']);
            if (preg_match('/[dmy]/', preg_replace('/"[^"]*"|\[[^\]]*\]/', '', $code))) $customDate[(int) $nf['numFmtId']] = true;
        }
        $i = 0;
        if (isset($st->cellXfs)) foreach ($st->cellXfs->xf as $xf) {
            $id = (int) $xf['numFmtId'];
            if (($id >= 14 && $id <= 22) || isset($customDate[$id])) $dateStyles[$i] = true;
            $i++;
        }
    }

    $sx = simplexml_load_string($sheetXml, 'SimpleXMLElement', LIBXML_NONET);
    $rows = [];
    foreach ($sx->sheetData->row as $row) {
        $r = [];
        foreach ($row->c as $c) {
            $ref = (string) $c['r'];
            $col = col_index(preg_replace('/\d+/', '', $ref));
            $type = (string) $c['t'];
            $v = (string) ($c->v ?? '');
            if ($type === 's') $v = $shared[(int) $v] ?? '';
            elseif ($type === 'inlineStr') $v = (string) ($c->is->t ?? '');
            elseif ($type === 'b') $v = $v ? '1' : '0';
            elseif ($v !== '' && is_numeric($v) && isset($dateStyles[(int) $c['s']])) {
                $v = date('Y-m-d', (int) round(((float) $v - 25569) * 86400));
            }
            $r[$col] = trim($v);
        }
        if (!$r) continue;
        $max = max(array_keys($r));
        $line = [];
        for ($i = 0; $i <= $max; $i++) $line[] = $r[$i] ?? '';
        if (count(array_filter($line, fn($x) => $x !== '')) === 0) continue;
        $rows[] = $line;
    }
    return $rows;
}

function col_index(string $letters): int
{
    $n = 0;
    foreach (str_split(strtoupper($letters)) as $ch) $n = $n * 26 + (ord($ch) - 64);
    return $n - 1;
}

function import_fields(): array
{
    return [
        'doc' => 'RUT / documento *',
        'nombre' => 'Nombre *',
        'apellido' => 'Apellido',
        'nombre_completo' => 'Nombre completo (si viene en una sola columna)',
        'email' => 'Correo',
        'telefono' => 'Teléfono',
        'plan' => 'Plan',
        'modalidad' => 'Modalidad (mensual, trimestral…)',
        'fecha_inicio' => 'Fecha inicio',
        'fecha_fin' => 'Fecha vencimiento',
        'estado' => 'Estado (activo/inactivo)',
    ];
}

function import_guess_map(array $headers): array
{
    $map = [];
    $syn = [
        'doc' => ['rut', 'run', 'documento', 'dni', 'pasaporte', 'id'],
        'nombre' => ['nombre', 'nombres', 'name'],
        'apellido' => ['apellido', 'apellidos'],
        'nombre_completo' => ['nombre completo', 'alumno', 'cliente', 'socio'],
        'email' => ['correo', 'email', 'mail', 'e-mail', 'correo electrónico', 'correo electronico'],
        'telefono' => ['telefono', 'teléfono', 'celular', 'fono', 'whatsapp', 'móvil', 'movil'],
        'plan' => ['plan', 'membresia', 'membresía'],
        'modalidad' => ['modalidad', 'periodo', 'período', 'tipo pago'],
        'fecha_inicio' => ['inicio', 'fecha inicio', 'desde', 'fecha de inicio', 'alta'],
        'fecha_fin' => ['vencimiento', 'vence', 'fecha fin', 'hasta', 'fecha termino', 'fecha término', 'fin'],
        'estado' => ['estado', 'activo', 'status'],
    ];
    foreach ($headers as $i => $h) {
        $hn = mb_strtolower(trim((string) $h));
        foreach ($syn as $field => $words) {
            if (isset($map[$field])) continue;
            if (in_array($hn, $words, true)) {
                $map[$field] = $i;
                continue 2;
            }
        }
    }
    foreach ($headers as $i => $h) {
        $hn = mb_strtolower(trim((string) $h));
        foreach ($syn as $field => $words) {
            if (isset($map[$field]) || in_array($i, $map, true)) continue;
            foreach ($words as $w) if (str_contains($hn, $w)) {
                $map[$field] = $i;
                continue 3;
            }
        }
    }
    return $map;
}

function parse_fecha(string $s): ?string
{
    $s = trim($s);
    if ($s === '') return null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) return substr($s, 0, 10);
    if (preg_match('~^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{2,4})$~', $s, $m)) {
        $y = strlen($m[3]) === 2 ? '20' . $m[3] : $m[3];
        if (checkdate((int) $m[2], (int) $m[1], (int) $y)) return sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]);
    }
    if (is_numeric($s) && (float) $s > 30000 && (float) $s < 80000) return date('Y-m-d', (int) round(((float) $s - 25569) * 86400));
    return null;
}

function parse_modalidad(string $s): string
{
    $s = mb_strtolower($s);
    foreach (['anual', 'semestral', 'trimestral', 'mensual'] as $m) if (str_contains($s, $m)) return $m;
    if (str_contains($s, '12')) return 'anual';
    if (str_contains($s, '6')) return 'semestral';
    if (str_contains($s, '3')) return 'trimestral';
    return 'mensual';
}

/** Valida y (opcionalmente) aplica. Devuelve resumen con filas y errores. */
function import_process(array $rows, array $map, bool $hasHeader, bool $apply, int $by): array
{
    $planes = [];
    foreach (qa('SELECT id, nombre FROM planes') as $p) $planes[mb_strtolower($p['nombre'])] = (int) $p['id'];
    $data = $hasHeader ? array_slice($rows, 1) : $rows;
    $res = ['total' => count($data), 'ok' => 0, 'creados' => 0, 'actualizados' => 0, 'errores' => [], 'duplicados' => [], 'preview' => []];
    $seen = [];
    $get = fn($r, $f) => isset($map[$f]) && $map[$f] !== '' ? trim((string) ($r[(int) $map[$f]] ?? '')) : '';
    foreach ($data as $i => $r) {
        $fila = $i + ($hasHeader ? 2 : 1);
        $docRaw = $get($r, 'doc');
        $err = [];
        $tipo = 'rut';
        if ($docRaw === '') $err[] = 'sin documento';
        elseif (rut_valid($docRaw)) $doc = rut_normalize($docRaw);
        elseif (preg_match('/^[0-9.\-kK ]+$/', $docRaw)) $err[] = 'RUT inválido (' . $docRaw . ')';
        else {
            $tipo = 'pasaporte';
            $doc = doc_normalize('pasaporte', $docRaw);
        }
        $nombre = $get($r, 'nombre');
        $apellido = $get($r, 'apellido');
        if ($nombre === '' && ($nc = $get($r, 'nombre_completo')) !== '') {
            $parts = preg_split('/\s+/', $nc);
            $nombre = array_shift($parts);
            $apellido = implode(' ', $parts);
        }
        if ($nombre === '') $err[] = 'sin nombre';
        $email = mb_strtolower($get($r, 'email'));
        if ($email !== '' && !valid_email($email)) {
            $err[] = 'correo inválido (se omite)';
            $email = '';
        }
        $planTxt = mb_strtolower($get($r, 'plan'));
        $planId = null;
        foreach ($planes as $pn => $pid) if ($planTxt !== '' && str_contains($planTxt, $pn)) $planId = $pid;
        $fi = parse_fecha($get($r, 'fecha_inicio'));
        $ff = parse_fecha($get($r, 'fecha_fin'));
        $modalidad = parse_modalidad($get($r, 'modalidad'));
        $estado = mb_strtolower($get($r, 'estado'));
        $activo = !in_array($estado, ['inactivo', 'no', '0', 'baja', 'suspendido'], true);

        $bloqueante = array_filter($err, fn($x) => !str_contains($x, 'se omite'));
        if (isset($doc) && !$bloqueante) {
            $k = $tipo . ':' . $doc;
            if (isset($seen[$k])) {
                $res['duplicados'][] = "Fila $fila: documento repetido (también en fila {$seen[$k]})";
                unset($doc);
                continue;
            }
            $seen[$k] = $fila;
        }
        if ($planTxt !== '' && !$planId) $err[] = 'plan "' . $planTxt . '" no existe (se importa sin membresía)';
        if ($planId && !$ff) $err[] = 'sin fecha de vencimiento (se importa sin membresía)';
        if ($err) $res['errores'][] = "Fila $fila: " . implode(', ', $err);
        if ($bloqueante) {
            unset($doc);
            continue;
        }
        $res['ok']++;
        if (count($res['preview']) < 15) $res['preview'][] = compact('doc', 'tipo', 'nombre', 'apellido', 'email', 'planTxt', 'fi', 'ff', 'modalidad', 'activo');
        if ($apply) {
            $u = q1('SELECT * FROM users WHERE doc_tipo = ? AND doc_num = ?', [$tipo, $doc]);
            $tel = $get($r, 'telefono');
            if ($u) {
                if ($u['rol'] !== 'alumno') {
                    $res['errores'][] = "Fila $fila: el documento pertenece a un usuario del staff, no se modifica";
                    unset($doc);
                    continue;
                }
                $upd = ['nombre' => $nombre, 'activo' => $activo ? 1 : 0];
                if ($apellido !== '') $upd['apellido'] = $apellido;
                if ($email !== '') $upd['email'] = $email;
                if ($tel !== '') $upd['telefono'] = $tel;
                update('users', $upd, 'id = ?', [$u['id']]);
                $userId = (int) $u['id'];
                $res['actualizados']++;
            } else {
                $userId = insert('users', ['rol' => 'alumno', 'doc_tipo' => $tipo, 'doc_num' => $doc, 'nombre' => $nombre, 'apellido' => $apellido,
                    'email' => $email ?: null, 'telefono' => $tel ?: null, 'activo' => $activo ? 1 : 0, 'sede_id' => sede_id()]);
                $res['creados']++;
            }
            if ($planId && $ff) {
                $fi = $fi ?: date('Y-m-d', strtotime($ff . ' -1 month'));
                $ex = q1('SELECT id FROM membresias WHERE user_id = ? AND plan_id = ? AND fecha_fin = ?', [$userId, $planId, $ff]);
                if (!$ex) insert('membresias', ['user_id' => $userId, 'plan_id' => $planId, 'modalidad' => $modalidad, 'fecha_inicio' => $fi, 'fecha_fin' => $ff, 'origen' => 'excel']);
            }
        }
        unset($doc);
    }
    return $res;
}

/** Escapa celdas para exportar CSV sin inyección de fórmulas. */
function csv_safe($v): string
{
    $v = (string) $v;
    if ($v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true)) $v = "'" . $v;
    return $v;
}
