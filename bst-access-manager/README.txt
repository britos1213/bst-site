BST ACCESS MANAGER V1.0

INSTALAÇÃO RECOMENDADA

1) Copie a pasta inteira "bst-access-manager" para:
   C:\xampp\htdocs\bst-access-manager

2) No phpMyAdmin, selecione o banco:
   bst_access_manager

3) Importe:
   database\v1_0.sql

4) Abra o Prompt de Comando:
   cd C:\xampp\htdocs\bst-access-manager
   composer install

5) Ajuste, se necessário:
   config\database.php

6) Acesse uma única vez:
   http://192.168.30.254/bst-access-manager/public/install_admin.php
   e crie o primeiro administrador.

7) Depois acesse:
   http://192.168.30.254/bst-access-manager/public/login.php

RECURSOS V1.0
- Login administrativo
- Dashboard
- Solicitações
- Aprovar/Rejeitar no banco
- Usuários aprovados
- Visualização dos sites bloqueados
- Logs administrativos
- Layout responsivo único

IMPORTANTE
A V1.0 não altera usuários no MikroTik automaticamente.
Ela preserva a base atual e organiza o sistema.
A V1.1 integrará:
- criar/excluir/bloquear usuário no MikroTik
- alterar senha
- usuários online
- desconectar sessão
- Hotspot
- VLANs
- bloqueio DNS pelo painel

CONFIG MIKROTIK
Arquivo:
config\mikrotik.php

Host preparado:
192.168.30.1
Usuário:
bstapi

Não coloque a senha real no arquivo distribuído. Edite localmente.
