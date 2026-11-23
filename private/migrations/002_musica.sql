-- v1.1 · Buscador de canciones (Spotify con respaldo en catálogo de Apple)
ALTER TABLE muro_posts ADD COLUMN musica_fuente TEXT;
ALTER TABLE muro_posts ADD COLUMN musica_titulo TEXT;
ALTER TABLE muro_posts ADD COLUMN musica_artista TEXT;
ALTER TABLE muro_posts ADD COLUMN musica_portada TEXT;
ALTER TABLE muro_posts ADD COLUMN musica_url TEXT;

ALTER TABLE canciones ADD COLUMN musica_fuente TEXT;
ALTER TABLE canciones ADD COLUMN musica_titulo TEXT;
ALTER TABLE canciones ADD COLUMN musica_artista TEXT;
ALTER TABLE canciones ADD COLUMN musica_portada TEXT;
ALTER TABLE canciones ADD COLUMN musica_url TEXT;

CREATE TABLE IF NOT EXISTS musica_cache (
  q TEXT PRIMARY KEY,
  data TEXT NOT NULL,
  created_at INTEGER NOT NULL
);
