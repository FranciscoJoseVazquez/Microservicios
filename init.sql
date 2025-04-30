-- Crear la base de datos (PostgreSQL no tiene IF NOT EXISTS dentro de CREATE DATABASE en scripts multi-db)
-- Esto se hace mejor fuera del script en tiempo de inicialización si es necesario

-- Crear las tablas (asegúrate de estar conectado a la base 'EstructuraMicroservicios')

CREATE TABLE IF NOT EXISTS Tipo_token (
    id SERIAL PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL
);

CREATE TABLE IF NOT EXISTS limite_token (
    id SERIAL PRIMARY KEY,
    id_tipo INT NOT NULL,
    NumVeces INT NOT NULL,
    FOREIGN KEY (id_tipo) REFERENCES Tipo_token(id)
);

CREATE TABLE IF NOT EXISTS Tokens (
    token VARCHAR(255) PRIMARY KEY,
    estado VARCHAR(10) CHECK (estado IN ('activo', 'expirado')) DEFAULT 'activo',
    id_limite_token INT,
    FOREIGN KEY (id_limite_token) REFERENCES limite_token(id)
);

CREATE TABLE IF NOT EXISTS Usos (
    id SERIAL PRIMARY KEY,
    token VARCHAR(255) NOT NULL,
    fecha_uso TIMESTAMP NOT NULL,
    FOREIGN KEY (token) REFERENCES Tokens(token)
);

-- Insertar datos
INSERT INTO Tipo_token (id, tipo) VALUES
(1, 'finito'),
(2, 'mensual')
ON CONFLICT DO NOTHING;

INSERT INTO limite_token (id, id_tipo, NumVeces) VALUES
(1, 1, 100), 
(2, 2, 100)
ON CONFLICT DO NOTHING;

INSERT INTO Tokens (token, estado, id_limite_token) VALUES
('1234abcd', 'activo', 1),  
('5678efgh', 'activo', 2)
ON CONFLICT DO NOTHING;
