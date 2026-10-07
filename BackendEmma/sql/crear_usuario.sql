-- =====================================================================
-- Crear un usuario administrador directamente por SQL
-- =====================================================================
-- MySQL/MariaDB no pueden generar hashes bcrypt, así que el hash se
-- genera antes con PHP y se pega en la query. NUNCA guardes la
-- contraseña en texto plano en la base ni en el repositorio.
--
-- 1) Generar una contraseña aleatoria fuerte y su hash (copiar ambas):
--
--    php -r '$p = substr(strtr(base64_encode(random_bytes(24)), "+/", "-_"), 0, 24); echo "Contraseña: $p", PHP_EOL, "Hash: ", password_hash($p, PASSWORD_BCRYPT, ["cost" => 12]), PHP_EOL;'
--
-- 2) Reemplazar los valores de abajo y ejecutar la query.
--
-- (Alternativa más simple, sin SQL:  php bin/console app:usuario:crear email@dominio.com "Nombre" --admin)
-- =====================================================================

INSERT INTO `usuario`
  (`NombreUsuario`, `Rol`, `UsuarioEmail`, `PasswordHash`, `Activo`, `IntentosFallidos`, `FechaCreacion`)
VALUES
  ('Administrador', 1, 'admin@emmaaccesorios.com',
   '$2y$12$REEMPLAZAR_POR_EL_HASH_GENERADO_EN_EL_PASO_1.............',
   1, 0, NOW());

-- Para cambiar la contraseña de un usuario existente:
-- UPDATE `usuario`
--    SET `PasswordHash` = '$2y$12$...', `IntentosFallidos` = 0, `BloqueadoHasta` = NULL
--  WHERE `UsuarioEmail` = 'admin@emmaaccesorios.com';
