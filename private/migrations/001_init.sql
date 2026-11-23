-- GorilaGym · esquema inicial v1 (SQLite)

CREATE TABLE IF NOT EXISTS settings (
  key TEXT PRIMARY KEY,
  value TEXT
);

CREATE TABLE IF NOT EXISTS sedes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nombre TEXT NOT NULL,
  direccion TEXT,
  lat REAL,
  lng REAL,
  radio_m INTEGER DEFAULT 100,
  activa INTEGER DEFAULT 1
);

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  rol TEXT NOT NULL CHECK (rol IN ('superadmin','jefe','coach','alumno')),
  doc_tipo TEXT NOT NULL DEFAULT 'rut' CHECK (doc_tipo IN ('rut','pasaporte','dni','otro')),
  doc_num TEXT NOT NULL,
  nombre TEXT NOT NULL,
  apellido TEXT DEFAULT '',
  email TEXT,
  telefono TEXT,
  password_hash TEXT,
  activo INTEGER NOT NULL DEFAULT 1,
  sede_id INTEGER REFERENCES sedes(id),
  muro_opt_in INTEGER NOT NULL DEFAULT 0,
  muro_logros INTEGER NOT NULL DEFAULT 0,
  muro_mostrar_pesos INTEGER NOT NULL DEFAULT 0,
  geo_opt_in INTEGER NOT NULL DEFAULT 0,
  pwa_hint_visto INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  last_login TEXT
);
CREATE UNIQUE INDEX IF NOT EXISTS ux_users_doc ON users(doc_tipo, doc_num);
CREATE INDEX IF NOT EXISTS ix_users_rol ON users(rol);

CREATE TABLE IF NOT EXISTS permissions (
  key TEXT PRIMARY KEY,
  grupo TEXT NOT NULL,
  nombre TEXT NOT NULL,
  descripcion TEXT,
  ambito TEXT NOT NULL DEFAULT 'staff' CHECK (ambito IN ('staff','alumno')),
  critico INTEGER NOT NULL DEFAULT 0,
  orden INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS role_permissions (
  rol TEXT NOT NULL,
  perm_key TEXT NOT NULL REFERENCES permissions(key),
  PRIMARY KEY (rol, perm_key)
);

CREATE TABLE IF NOT EXISTS otp_codes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id),
  code_hash TEXT NOT NULL,
  expires_at INTEGER NOT NULL,
  used_at INTEGER,
  attempts INTEGER NOT NULL DEFAULT 0,
  ip TEXT,
  created_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS ix_otp_user ON otp_codes(user_id, created_at);

CREATE TABLE IF NOT EXISTS remember_tokens (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id),
  selector TEXT NOT NULL UNIQUE,
  validator_hash TEXT NOT NULL,
  expires_at INTEGER NOT NULL,
  ua TEXT,
  created_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS rate_limits (
  key TEXT PRIMARY KEY,
  hits INTEGER NOT NULL,
  window_start INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS audit_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER,
  accion TEXT NOT NULL,
  entidad TEXT,
  entidad_id INTEGER,
  detalle TEXT,
  ip TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

-- Planes y membresías
CREATE TABLE IF NOT EXISTS planes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nombre TEXT NOT NULL UNIQUE,
  tipo TEXT NOT NULL DEFAULT 'coach' CHECK (tipo IN ('coach','libre')),
  descripcion TEXT,
  requiere_estudiante INTEGER NOT NULL DEFAULT 0,
  precio_mensual INTEGER,
  precio_trimestral INTEGER,
  precio_semestral INTEGER,
  precio_anual INTEGER,
  beneficio TEXT,
  beneficio_modalidades TEXT,
  activo INTEGER NOT NULL DEFAULT 1,
  orden INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS plan_horarios (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  plan_id INTEGER NOT NULL REFERENCES planes(id) ON DELETE CASCADE,
  dia INTEGER NOT NULL CHECK (dia BETWEEN 1 AND 7),
  hora_inicio TEXT NOT NULL,
  hora_fin TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS membresias (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id),
  plan_id INTEGER NOT NULL REFERENCES planes(id),
  modalidad TEXT NOT NULL DEFAULT 'mensual' CHECK (modalidad IN ('mensual','trimestral','semestral','anual')),
  fecha_inicio TEXT NOT NULL,
  fecha_fin TEXT NOT NULL,
  pausada INTEGER NOT NULL DEFAULT 0,
  origen TEXT NOT NULL DEFAULT 'manual',
  regalo_entregado_at TEXT,
  notas TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX IF NOT EXISTS ix_memb_user ON membresias(user_id, fecha_fin);

CREATE TABLE IF NOT EXISTS pausas (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  membresia_id INTEGER NOT NULL REFERENCES membresias(id),
  fecha_incidente TEXT NOT NULL,
  descripcion TEXT NOT NULL,
  desde TEXT NOT NULL,
  hasta TEXT,
  dias INTEGER,
  estado TEXT NOT NULL DEFAULT 'pendiente' CHECK (estado IN ('pendiente','activa','cerrada','rechazada')),
  registrado_por INTEGER REFERENCES users(id),
  aprobado_por INTEGER REFERENCES users(id),
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS acreditaciones (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id),
  documento TEXT NOT NULL,
  validado_por INTEGER REFERENCES users(id),
  fecha_validacion TEXT NOT NULL,
  vence TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS pases_diarios (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER REFERENCES users(id),
  nombre TEXT NOT NULL,
  doc_num TEXT,
  fecha TEXT NOT NULL,
  monto INTEGER,
  registrado_por INTEGER REFERENCES users(id),
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

-- Ficha del alumno (datos de salud: acceso restringido por permiso)
CREATE TABLE IF NOT EXISTS fichas (
  user_id INTEGER PRIMARY KEY REFERENCES users(id),
  objetivo TEXT,
  nivel TEXT DEFAULT 'principiante',
  dias_semana INTEGER,
  restricciones TEXT,
  restricciones_detalle TEXT,
  cuestionario TEXT,
  consentimiento_salud_at TEXT,
  updated_at TEXT
);

CREATE TABLE IF NOT EXISTS solicitudes_eliminacion (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id),
  motivo TEXT,
  estado TEXT NOT NULL DEFAULT 'pendiente',
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  resuelto_por INTEGER,
  resuelto_at TEXT
);

-- Catálogo
CREATE TABLE IF NOT EXISTS grupos_musculares (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nombre TEXT NOT NULL UNIQUE,
  orden INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS maquinas (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  sede_id INTEGER REFERENCES sedes(id),
  nombre TEXT NOT NULL,
  zona TEXT,
  foto TEXT,
  estado TEXT NOT NULL DEFAULT 'ok' CHECK (estado IN ('ok','mantencion')),
  token TEXT NOT NULL UNIQUE,
  es_zona INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS ejercicios (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nombre TEXT NOT NULL,
  maquina_id INTEGER REFERENCES maquinas(id),
  grupo_id INTEGER REFERENCES grupos_musculares(id),
  grupos_secundarios TEXT,
  nivel TEXT DEFAULT 'todos',
  instrucciones TEXT,
  errores TEXT,
  foto TEXT,
  video TEXT,
  seg_por_serie INTEGER NOT NULL DEFAULT 45,
  descanso_seg INTEGER NOT NULL DEFAULT 90,
  series_def INTEGER NOT NULL DEFAULT 3,
  reps_min INTEGER NOT NULL DEFAULT 8,
  reps_max INTEGER NOT NULL DEFAULT 12,
  incremento_kg REAL NOT NULL DEFAULT 2.5,
  restricciones TEXT,
  token TEXT UNIQUE,
  activo INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX IF NOT EXISTS ix_ej_maq ON ejercicios(maquina_id);
CREATE INDEX IF NOT EXISTS ix_ej_grupo ON ejercicios(grupo_id);

CREATE TABLE IF NOT EXISTS ejercicio_alternativas (
  ejercicio_id INTEGER NOT NULL REFERENCES ejercicios(id) ON DELETE CASCADE,
  alternativa_id INTEGER NOT NULL REFERENCES ejercicios(id) ON DELETE CASCADE,
  PRIMARY KEY (ejercicio_id, alternativa_id)
);

CREATE TABLE IF NOT EXISTS plantillas (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nombre TEXT NOT NULL,
  descripcion TEXT,
  nivel TEXT DEFAULT 'todos',
  oficial INTEGER NOT NULL DEFAULT 1,
  creada_por INTEGER REFERENCES users(id),
  activo INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS plantilla_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  plantilla_id INTEGER NOT NULL REFERENCES plantillas(id) ON DELETE CASCADE,
  orden INTEGER NOT NULL,
  bloque INTEGER NOT NULL,
  ejercicio_id INTEGER NOT NULL REFERENCES ejercicios(id),
  series INTEGER NOT NULL,
  reps_min INTEGER NOT NULL,
  reps_max INTEGER NOT NULL,
  descanso_seg INTEGER NOT NULL
);

-- Sesiones de entrenamiento
CREATE TABLE IF NOT EXISTS sesiones (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  alumno_id INTEGER NOT NULL REFERENCES users(id),
  coach_id INTEGER REFERENCES users(id),
  sede_id INTEGER REFERENCES sedes(id),
  modo TEXT NOT NULL DEFAULT 'coach' CHECK (modo IN ('coach','libre')),
  estado TEXT NOT NULL DEFAULT 'planificada' CHECK (estado IN ('planificada','en_curso','finalizada','cancelada')),
  titulo TEXT,
  est_min INTEGER,
  inicio TEXT,
  fin_coach TEXT,
  fin TEXT,
  feedback INTEGER,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX IF NOT EXISTS ix_ses_alumno ON sesiones(alumno_id, created_at);
CREATE INDEX IF NOT EXISTS ix_ses_estado ON sesiones(estado);

CREATE TABLE IF NOT EXISTS sesion_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  sesion_id INTEGER NOT NULL REFERENCES sesiones(id) ON DELETE CASCADE,
  orden INTEGER NOT NULL,
  bloque INTEGER NOT NULL,
  ejercicio_id INTEGER NOT NULL REFERENCES ejercicios(id),
  series INTEGER NOT NULL,
  reps_min INTEGER NOT NULL,
  reps_max INTEGER NOT NULL,
  peso_sugerido REAL,
  descanso_seg INTEGER NOT NULL,
  estado TEXT NOT NULL DEFAULT 'pendiente' CHECK (estado IN ('pendiente','en_curso','hecho','omitido')),
  compartido INTEGER NOT NULL DEFAULT 0,
  extra INTEGER NOT NULL DEFAULT 0,
  inicio TEXT,
  fin TEXT
);
CREATE INDEX IF NOT EXISTS ix_items_ses ON sesion_items(sesion_id, orden);

CREATE TABLE IF NOT EXISTS series_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  item_id INTEGER NOT NULL REFERENCES sesion_items(id) ON DELETE CASCADE,
  sesion_id INTEGER NOT NULL REFERENCES sesiones(id) ON DELETE CASCADE,
  alumno_id INTEGER NOT NULL REFERENCES users(id),
  ejercicio_id INTEGER NOT NULL REFERENCES ejercicios(id),
  n INTEGER NOT NULL,
  reps INTEGER NOT NULL,
  peso REAL NOT NULL DEFAULT 0,
  client_uuid TEXT UNIQUE,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX IF NOT EXISTS ix_series_alumno_ej ON series_log(alumno_id, ejercicio_id, created_at);

CREATE TABLE IF NOT EXISTS ocupacion (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  maquina_id INTEGER NOT NULL REFERENCES maquinas(id),
  item_id INTEGER REFERENCES sesion_items(id) ON DELETE SET NULL,
  alumno_id INTEGER REFERENCES users(id),
  desde INTEGER NOT NULL,
  hasta_est INTEGER NOT NULL,
  liberada_at INTEGER
);
CREATE INDEX IF NOT EXISTS ix_ocup_maq ON ocupacion(maquina_id, liberada_at);

CREATE TABLE IF NOT EXISTS checkins (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id),
  sede_id INTEGER REFERENCES sedes(id),
  metodo TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX IF NOT EXISTS ix_checkins_user ON checkins(user_id, created_at);

CREATE TABLE IF NOT EXISTS notas_alumno (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  alumno_id INTEGER NOT NULL REFERENCES users(id),
  autor_id INTEGER NOT NULL REFERENCES users(id),
  tipo TEXT NOT NULL DEFAULT 'nota' CHECK (tipo IN ('nota','traspaso')),
  texto TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS calificaciones (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  sesion_id INTEGER NOT NULL UNIQUE REFERENCES sesiones(id),
  coach_id INTEGER NOT NULL REFERENCES users(id),
  alumno_id INTEGER NOT NULL REFERENCES users(id),
  tecnica INTEGER NOT NULL,
  atencion INTEGER NOT NULL,
  rutina INTEGER NOT NULL,
  comentario TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS mediciones (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id),
  fecha TEXT NOT NULL,
  peso REAL, cintura REAL, cadera REAL, pecho REAL, brazo REAL, muslo REAL,
  notas TEXT
);

CREATE TABLE IF NOT EXISTS logros (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id),
  tipo TEXT NOT NULL,
  ejercicio_id INTEGER REFERENCES ejercicios(id),
  valor REAL,
  texto TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

-- Muro
CREATE TABLE IF NOT EXISTS muro_posts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id),
  tipo TEXT NOT NULL CHECK (tipo IN ('estado','logro')),
  texto TEXT,
  spotify_tipo TEXT,
  spotify_id TEXT,
  sesion_id INTEGER REFERENCES sesiones(id),
  logro_id INTEGER REFERENCES logros(id),
  oculto INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX IF NOT EXISTS ix_muro_fecha ON muro_posts(created_at);

CREATE TABLE IF NOT EXISTS muro_reacciones (
  post_id INTEGER NOT NULL REFERENCES muro_posts(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id),
  emoji TEXT NOT NULL,
  PRIMARY KEY (post_id, user_id, emoji)
);

CREATE TABLE IF NOT EXISTS muro_reportes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  post_id INTEGER NOT NULL REFERENCES muro_posts(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id),
  motivo TEXT,
  resuelto INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS canciones (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id),
  spotify_tipo TEXT NOT NULL,
  spotify_id TEXT NOT NULL,
  estado TEXT NOT NULL DEFAULT 'pendiente' CHECK (estado IN ('pendiente','sonada','descartada')),
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

-- Comunicación
CREATE TABLE IF NOT EXISTS avisos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  titulo TEXT NOT NULL,
  cuerpo TEXT,
  desde TEXT NOT NULL,
  hasta TEXT,
  push INTEGER NOT NULL DEFAULT 0,
  creado_por INTEGER REFERENCES users(id),
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS push_subs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id),
  endpoint TEXT NOT NULL UNIQUE,
  p256dh TEXT,
  auth TEXT,
  ua TEXT,
  created_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS notificaciones (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id),
  tipo TEXT NOT NULL,
  titulo TEXT NOT NULL,
  cuerpo TEXT,
  url TEXT,
  ref TEXT,
  enviar_at INTEGER NOT NULL,
  enviada_at INTEGER,
  entregada_at INTEGER,
  cancelada INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS ix_notif_pend ON notificaciones(enviada_at, enviar_at);

CREATE TABLE IF NOT EXISTS turnos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id),
  dia INTEGER NOT NULL CHECK (dia BETWEEN 1 AND 7),
  desde TEXT NOT NULL,
  hasta TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS imports (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  archivo TEXT NOT NULL,
  filas INTEGER DEFAULT 0,
  creados INTEGER DEFAULT 0,
  actualizados INTEGER DEFAULT 0,
  errores TEXT,
  creado_por INTEGER REFERENCES users(id),
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS reportes_mensuales (
  user_id INTEGER NOT NULL,
  periodo TEXT NOT NULL,
  enviado_at TEXT,
  PRIMARY KEY (user_id, periodo)
);
