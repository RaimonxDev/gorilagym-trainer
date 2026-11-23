-- v1.3 · Sesión en un solo dispositivo, foto de perfil, estados predeterminados del muro, historias de Instagram
ALTER TABLE users ADD COLUMN session_token TEXT;
ALTER TABLE users ADD COLUMN foto TEXT;

INSERT OR IGNORE INTO settings(key, value) VALUES ('sesion_unica_alumnos', '1');
INSERT OR IGNORE INTO settings(key, value) VALUES ('sesion_unica_staff', '0');
INSERT OR IGNORE INTO settings(key, value) VALUES ('brand_instagram', '');
INSERT OR IGNORE INTO settings(key, value) VALUES ('muro_estados', 'Hoy me toca pierna 🦵
Hoy me toca pecho 💪
Hoy me toca espalda
Hoy me toca hombros
Hoy me toca brazos
Día de full body
Cardio y core
Volví a entrenar 🙌
Rompí un récord 🏆
Con todo hoy 🔥
Día pesado, pero vine
Entrenando con mi partner');
