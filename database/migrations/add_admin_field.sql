-- Adicionar campo is_admin na tabela users para controle de acesso ao analytics
ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0 AFTER email_verified_at;

-- Criar índice para consultas de admin
ALTER TABLE users ADD INDEX idx_is_admin (is_admin);

-- Atualizar um usuário para ser admin (substitua pelo ID correto)
-- UPDATE users SET is_admin = 1 WHERE email = 'admin@meifinanceiro.com';