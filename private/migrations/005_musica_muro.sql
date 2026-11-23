-- v1.5 · Vuelve el buscador de canciones del muro (la cola de canciones de la sala sigue fuera)
CREATE TABLE IF NOT EXISTS musica_cache (
  q TEXT PRIMARY KEY,
  data TEXT NOT NULL,
  created_at INTEGER NOT NULL
);
