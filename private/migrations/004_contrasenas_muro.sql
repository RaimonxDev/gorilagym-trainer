-- v1.4 · contraseñas obligatorias, cambio de correo verificado, muro con límite diario y borrado, series automáticas, sin música
ALTER TABLE otp_codes ADD COLUMN proposito TEXT NOT NULL DEFAULT 'login';
ALTER TABLE otp_codes ADD COLUMN dato TEXT;
ALTER TABLE muro_posts ADD COLUMN eliminado_at TEXT;
ALTER TABLE muro_posts ADD COLUMN auto INTEGER NOT NULL DEFAULT 0;
ALTER TABLE series_log ADD COLUMN auto INTEGER NOT NULL DEFAULT 0;

-- Los estados automáticos generados por la rutina (antes sin marca) quedan como automáticos
UPDATE muro_posts SET auto = 1 WHERE tipo = 'estado' AND sesion_id IS NOT NULL;

INSERT OR IGNORE INTO settings(key, value) VALUES ('muro_posts_dia', '1');

-- Se retira la función de música
DELETE FROM role_permissions WHERE perm_key IN ('alumno.canciones', 'canciones.gestionar');
DELETE FROM permissions WHERE key IN ('alumno.canciones', 'canciones.gestionar');
DELETE FROM settings WHERE key LIKE 'spotify_%';
DROP TABLE IF EXISTS musica_cache;
