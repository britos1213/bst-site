USE bst_access_manager;

ALTER TABLE solicitacoes
    ADD COLUMN IF NOT EXISTS tipo_solicitado VARCHAR(30) NULL AFTER email,
    ADD COLUMN IF NOT EXISTS perfil_aprovado VARCHAR(100) NULL AFTER vlan_id,
    ADD COLUMN IF NOT EXISTS vlan_aprovada INT NULL AFTER perfil_aprovado,
    ADD COLUMN IF NOT EXISTS aprovado_em DATETIME NULL AFTER analisado_em;

UPDATE solicitacoes
SET tipo_solicitado = COALESCE(NULLIF(tipo_solicitado,''), setor)
WHERE tipo_solicitado IS NULL OR tipo_solicitado='';
