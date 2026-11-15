# Como rodar o QloApps modificado do zero (PC novo)

Guia para subir o site em uma máquina nova a partir do clone do GitHub, e testar
os dois trabalhos: **Strategy no pagamento** (Maria Eduarda) e **Decorator no
cálculo de preço**.

> **Branch:** este guia assume que você clonou e está na branch
> `feature/decorator-pricing` — ela contém as duas partes integradas
> (merge do trabalho Strategy + refactor Decorator + correções).
>
> ```bash
> git clone -b feature/decorator-pricing https://github.com/SEU_USUARIO/QloApps.git
> cd QloApps
> ```

---

## 1. Pré-requisitos

| Requisito | Versão / Detalhe |
|---|---|
| **XAMPP** | **8.2.x** (Apache + MariaDB + PHP juntos) — o `composer.json` exige PHP `>=8.2 <8.6` |
| **Git** | qualquer versão recente |
| **Navegador** | para o instalador web e os testes |
| Extensões PHP | `curl, dom, gd, openssl, pdo_mysql, simplexml, soap, zip, fileinfo, mbstring` (o XAMPP traz todas; 3 precisam ser ativadas — passo 2.2) |

> **Importante (Windows):** clone o repositório **fora de pastas sincronizadas na
> nuvem** (OneDrive/Drive). Durante os testes, o OneDrive causou erros
> `Smarty: unable to write file` nos caches compilados. Sugestão: `C:\dev\QloApps`.

> As seções 2 a 10 usam **Windows/XAMPP**. Para **Linux Fedora**, pule para a
> **seção 11** (a partir dela, os comandos substituem os equivalentes do XAMPP;
> os testes das seções 8 e 9 são os mesmos, mudando só os caminhos dos comandos).

### 2. Instalar XAMPP e ativar extensões

**2.1 — Instalar** (uma opção via terminal):

```bash
winget install ApacheFriends.Xampp.8.2
```

ou baixe em <https://www.apachefriends.org/> (versão **PHP 8.2**) e instale em `C:\xampp`.

**2.2 — Ativar as extensões que o XAMPP traz desligadas** — abra
`C:\xampp\php\php.ini` e descomente (remova o `;`) estas 3 linhas:

```ini
extension=gd
extension=soap
extension=zip
```

Confira depois com `C:\xampp\php\php.exe -m` — as três devem aparecer na lista.

### 3. Subir Apache e MySQL

Abra `C:\xampp\xampp-control.exe` e clique em **Start** no **Apache** e no
**MySQL**. (Ou, sem o painel: `C:\xampp\apache\bin\httpd.exe` e
`C:\xampp\mysql\bin\mysqld.exe --defaults-file=C:/xampp/mysql/bin/my.ini --standalone`.)

Teste: <http://localhost/> deve abrir o dashboard do XAMPP.

### 4. Criar o banco de dados

```bash
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE qloapps CHARACTER SET utf8 COLLATE utf8_general_ci;"
```

(root sem senha é o padrão do XAMPP — suficiente para o ambiente de estudos.)

### 5. Expor o clone no servidor

Escolha **uma** das opções:

**Opção A — junction (recomendada: fonte única, sem cópia):**

```bash
cmd /c mklink /J C:\xampp\htdocs\qloapps "C:\dev\QloApps"
```

**Opção B — mover o clone para dentro do htdocs:**

```bash
git clone -b feature/decorator-pricing https://github.com/SEU_USUARIO/QloApps.git C:\xampp\htdocs\qloapps
```

### 6. Restaurar o instalador e rodar a instalação web

O commit de origem da branch deletou a pasta `install-dev` (instalador). Ela é
**necessária uma única vez** para instalar em uma máquina nova. Restaure a partir
do commit base (existe no histórico da branch):

```bash
git checkout e502b63 -- install-dev
```

Agora abra **<http://localhost/qloapps/install-dev/>** e siga o assistente:

1. **Language:** English (ou preferência) → Next
2. **License:** marque "I agree" → Next
3. **System compatibility:** deve passar (Next)
4. **Website information:**
   - Website name: `QloApps Estudos`
   - **Install demo data: YES** ← essencial (cria os quartos/planos usados nos testes)
   - Conta admin (exemplo usado no projeto): e-mail `admin@qloapps.local`, senha `QloApps2026!`
5. **System configuration (banco):**
   - Address: `localhost` — Database name: `qloapps` — Login: `root` — Password: *(vazio)* — Prefix: `qlo_`
   - "Test your database connection now!" → Next
6. Aguarde a instalação (tabelas, idiomas, módulos, tema — alguns minutos).
   No fim, anote os acessos:
   - **Front office:** <http://localhost/qloapps/>
   - **Back office:** <http://localhost/qloapps/admin-dev/>

Por segurança o site pede para remover o instalador após concluir:

```bash
git reset -- install-dev 2>nul & rmdir /s /q install-dev
```

*(ou simplesmente renomeie a pasta — o importante é que `install-dev` não fique
no lugar; se precisar reinstalar num PC novo, repita o `git checkout e502b63 -- install-dev`)*

### 7. Limpar caches gerados (se trocar de branch ou ver telas estranhas)

```bash
rm -f cache/class_index.php
rm -rf cache/smarty/compile/* cache/smarty/cache/*
```

---

## 8. Testar a parte da Maria Eduarda — Strategy no pagamento

**O que esperar:** na etapa de pagamento do checkout, as opções clássicas são
substituídas por 3 formas de pagamento (Cartão de Crédito/Stripe, PIX, Pagar na
Recepção/Offline). Ao confirmar, o backend escolhe a **estratégia** correspondente
via `PaymentStrategyFactory` e responde com o resultado dela (as estratégias são
*stubs* didáticos — nenhum pedido real é criado no banco).

**Passo a passo pela interface:**

1. Abra <http://localhost/qloapps/>, escolha datas qualquer e **Book Now** em um quarto.
2. Na página do carrinho (`quick-order`), clique **Proceed**.
3. Preencha **Guest Information** (nome, e-mail, senha, telefone) e salve — uma conta de cliente é criada.
4. Clique **Proceed** até a etapa **Payment Information** — deve aparecer "Escolha a forma de pagamento:".
5. Selecione **PIX** e clique **Confirmar Pedido** → alert: *"Pagamento PIX gerado! QR Code: 000201010211..."*
6. Selecione **Cartão de Crédito** e confirme → alert de sucesso com o "processado via Stripe".

**Verificando as 4 rotas da Strategy diretamente** (com um carrinho ativo, no
console do navegador na página do site, ou via `curl` com os cookies da sessão):

```js
fetch('index.php?controller=PaymentProcessor&ajax=true&payment_module=pix', {method:'POST'})
  .then(r => r.json()).then(console.log)
// → {"success":true,"data":{"status":"pending","message":"QR Code PIX gerado","qr_code":"000201..."}}
```

| `payment_module` | Estratégia usada | Resposta |
|---|---|---|
| `pix` | `PixPaymentStrategy` | `pending` + QR Code (stub) |
| `stripe` | `StripePaymentStrategy` | `success` + `transaction_id` (stub) |
| `offline` | `OfflinePaymentStrategy` | sucesso "aguardando confirmação no balcão" |
| outro (ex.: `qloduitkupayment`) | — (factory retorna null) | `success:false` "Estratégia de pagamento não implementada" |

**Arquivos do trabalho dela:** `Core/Business/Payment/PaymentOption.php`,
`Core/Business/Payment/Strategy/*` (interface, factory e 3 estratégias),
`controllers/front/PaymentProcessorController.php` e os templates
`themes/hotel-reservation-theme/order-payment.tpl` + `header.tpl`.

**Nota sobre a validação do carrinho:** o controller exige apenas carrinho ativo
e cliente logado/criado (`id_customer != 0`) — o checkout padrão do QloApps não
coleta endereço do hóspede, então **não** há exigência de endereço
(`id_address_invoice`). Se aparecer "Erro na validação do carrinho", basta
preencher o Guest Information antes de chegar à etapa de pagamento.

---

## 9. Testar a sua parte (Decorator) — cálculo de preço

**O que esperar:** o preço do quarto deixa de ser calculado por um método
monolítico e passa por uma **cadeia de Decorators** (`BasePrice` → `FeaturePricing`
→ taxa turística → serviços). Os 37 pontos de chamada não mudaram — o comportamento
é equivalente ao código original (comprovado pelo harness).

**Arquivos:** `modules/hotelreservationsystem/classes/HotelRoomTypePriceContext.php`,
`HotelRoomTypePriceComponent.php` (abstrato) e os componentes
`HotelRoomTypeBasePriceComponent`, `HotelRoomTypeFeaturePricingComponent`,
`HotelRoomTypeTourismTaxComponent`, `HotelRoomTypeAutoServicesComponent`.

### 9.1 — Aplicar os planos de preço de teste (fixture)

```bash
C:\xampp\mysql\bin\mysql.exe -u root qloapps < tests\fixtures_feature_pricing.sql
```

Cria 3 planos no room type de id **1** (General Rooms, criado pela demo data),
com janelas de datas largas e relativas ao dia da execução:

| Plano | Impacto | Janela (dias a partir de hoje) |
|---|---|---|
| Test Decrease 20% | -20% por noite | +10 a +20 |
| Test Increase Fixed 100 | +100 por noite | +25 a +35 |
| Test Fix 500 | preço fixado em 500 | +40 a +50 |

Confirme que o room type 1 existe: `SELECT id_product FROM qlo_htl_room_type;`
(se o id for outro, ajuste o `id_product = 1` dentro do SQL do fixture).

### 9.2 — Ver pela interface

1. Abra a página do quarto: <http://localhost/qloapps/> → Rooms → **General Rooms**
2. Reserve com **check-in hoje+11 e check-out hoje+14** (3 noites, todas dentro
   da janela do plano de -20%) e 1 quarto.
3. No carrinho, o **Total Rooms price** deve mostrar o preço-base **com 20% de
   desconto por noite** (o valor original riscado aparece acima). As duas áreas
   de total (lista de quartos e resumo de pagamento) devem **concordar**.
4. Troque as datas para a janela do plano "+100" ou "fix 500" e repita — o efeito
   muda conforme o plano, aplicado pela cadeia de Decorators.

### 9.3 — Provar a equivalência por linha de comando (harness)

```bash
cd C:\caminho\do\QloApps
C:\xampp\php\php.exe tests\pricing_characterization.php
```

Compara a pipeline Decorator com a cópia da implementação original em **13
cenários** (planos, occupancy, taxas, serviços, `use_reduc=0`, datas iguais…).
Resultado esperado: **13/13 PASS** (diferença 0.0000).

Probes de diagnóstico (usados para isolar um bug real durante o desenvolvimento —
mantidos para estudo):

```bash
C:\xampp\php\php.exe tests\probe_cart_pricing.php   # casos com contexto de carrinho
C:\xampp\php\php.exe tests\probe_matrix.php         # matriz occupancy × serviços
C:\xampp\php\php.exe tests\probe_day_loop.php       # loop dia a dia da implementação legada
```

> O arquivo `DECORATOR_IMPLEMENTACAO.md` documenta o refactor completo
> (cadeia, invariantes preservadas e decisões).

---

## 10. Problemas comuns

| Sintoma | Causa / Solução |
|---|---|
| Porta 80 ou 3306 ocupada | outro Apache/MySQL rodando — pare o serviço ou troque a porta |
| `Smarty: unable to write file ...` | pasta do clone dentro de OneDrive/Drive — mova para fora da sincronização |
| Classes novas não encontradas (Decorator) | apague `cache/class_index.php` e recarregue |
| Site pede para deletar a pasta `install` | instale e renomeie/remova `install-dev` (passo 6) |
| "Erro na validação do carrinho" ao pagar | carrinho sem cliente — preencha o Guest Information antes de pagar (seção 8) |
| Preços sem efeito dos planos | fixture não aplicado (9.1) ou datas fora das janelas — rode o fixture de novo (ele é idempotente e recalcula as janelas pelo dia atual) |
| Apache/MySQL não sobem pelo painel | rode `xampp-control.exe` como administrador, ou inicie os binários manualmente (passo 3) |

---

## 11. Versão Linux — Fedora

Equivalente completo das seções 2 a 7 (instalação) e 9.1/9.3 (fixture e harness)
usando os pacotes nativos do Fedora, sem XAMPP. Os testes das seções 8 e 9.2
são idênticos — só mudam os comandos de banco e PHP indicados abaixo.

### 11.1 Pré-requisitos (uma linha de dnf)

```bash
sudo dnf install -y httpd mariadb-server php php-cli php-pdo php-mysqlnd php-gd php-soap php-mbstring php-xml git
```

Notas importantes sobre o PHP do Fedora:

- As extensões **zip, curl, openssl, fileinfo, phar e dom/simplexml** já vêm
  habilitadas nos pacotes `php-common`/`php-xml` — **não existe php.ini para
  descomentar** (cada extensão é um arquivo em `/etc/php.d/`). Confira:
  `php -m | grep -E 'gd|soap|zip|pdo_mysql|mbstring|curl'`
- O PHP dos repositórios do Fedora é 8.3+ (dentro da faixa `>=8.2 <8.6`). Em
  Fedora antigo (< 37), ative o repositório Remi:
  `sudo dnf install https://rpms.remirepo.net/fedora/remi-release-$(rpm -E %fedora).rpm && sudo dnf module enable php:remi-8.2`

### 11.2 Subir os serviços

```bash
sudo systemctl enable --now httpd mariadb
```

Se o firewalld estiver ativo:

```bash
sudo firewall-cmd --permanent --add-service=http --add-service=https
sudo firewall-cmd --reload
```

### 11.3 Banco de dados

No Fedora, o `root` do MariaDB usa autenticação **unix_socket** — o PHP do
Apache **não** conecta como root sem senha. Crie um usuário dedicado:

```bash
sudo mysql <<'SQL'
CREATE DATABASE qloapps CHARACTER SET utf8 COLLATE utf8_general_ci;
CREATE USER 'qlo'@'localhost' IDENTIFIED BY 'qlo123';
GRANT ALL PRIVILEGES ON qloapps.* TO 'qlo'@'localhost';
FLUSH PRIVILEGES;
SQL
```

### 11.4 Clonar dentro do /var/www/html e ajustar SELinux

Clone **dentro de `/var/www/html`** (não dentro de `/home` — o SELinux bloqueia
leitura do diretório home pelo httpd):

```bash
cd /var/www/html
sudo git clone -b feature/decorator-pricing https://github.com/SEU_USUARIO/QloApps.git qloapps
sudo chown -R $USER:apache qloapps
git -C qloapps checkout e502b63 -- install-dev   # instalador, igual ao passo 6 do Windows
```

Dê ao httpd permissão de leitura **e escrita** (o instalador e o site escrevem em
`cache/`, `config/`, `img/`, `upload/`, `download/`, `log/`, `modules/`). Em
máquina de estudos, o caminho simples é marcar a pasta inteira como gravável
pelo httpd:

```bash
sudo dnf install -y policycoreutils-python-utils   # se 'semanage' não existir
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/html/qloapps(/.*)?"
sudo restorecon -R /var/www/html/qloapps
```

*(Isso é adequado para ambiente local de estudos; em produção o correto é
`httpd_sys_content_t` no geral e `httpd_sys_rw_content_t` apenas nos diretórios
graváveis.)*

### 11.5 AllowOverride — sem isso as URLs amigáveis quebram

O `httpd` do Fedora vem com `AllowOverride None`, o que faz o QloApps **ignorar
os `.htaccess`** (URLs amigáveis dão 404). Edite `/etc/httpd/conf/httpd.conf` e,
no bloco `<Directory "/var/www/html">`, troque `AllowOverride None` por
`AllowOverride All` — ou, num ambiente só de estudos:

```bash
sudo sed -i 's/AllowOverride None/AllowOverride All/' /etc/httpd/conf/httpd.conf
sudo systemctl restart httpd
```

### 11.6 Instalar o site (instalador web)

Abra **http://localhost/qloapps/install-dev/** e siga a seção 6 do Windows, com
**uma única diferença** — credenciais do banco (usuário criado em 11.3):

| Campo | Valor |
|---|---|
| Database server address | `localhost` |
| Database name | `qloapps` |
| Database login | `qlo` |
| Database password | `qlo123` |
| Tables prefix | `qlo_` |

Todo o resto é igual (demo data **YES**, conta admin, etc.). Ao finalizar,
remova o instalador:

```bash
git -C /var/www/html/qloapps reset -- install-dev
sudo rm -rf /var/www/html/qloapps/install-dev
```

E limpe os caches se necessário:

```bash
rm -f /var/www/html/qloapps/cache/class_index.php
rm -rf /var/www/html/qloapps/cache/smarty/compile/* /var/www/html/qloapps/cache/smarty/cache/*
```

### 11.7 Testar a parte da Maria Eduarda (Strategy)

**Idêntico à seção 8** — o fluxo é pela interface do site (não depende do
sistema operacional). Para verificar as rotas da factory pelo terminal, com o
cookie de sessão de um carrinho ativo:

```bash
curl -s -b "cookie_qloapps=<valor_do_cookie>" \
  "http://localhost/qloapps/index.php?controller=PaymentProcessor&ajax=true&payment_module=pix" \
  -X POST -H "Accept: application/json"
# → {"success":true,"data":{"status":"pending","message":"QR Code PIX gerado","qr_code":"000201..."}}
```

*(o caminho mais fácil continua sendo o console do navegador na página do site —
veja o snippet JavaScript na seção 8).*

### 11.8 Testar a sua parte (Decorator)

```bash
# fixture dos planos de preço (idempotente, janelas relativas ao dia atual)
sudo mysql qloapps < /var/www/html/qloapps/tests/fixtures_feature_pricing.sql

# harness de caracterização (esperado: 13/13 PASS)
php /var/www/html/qloapps/tests/pricing_characterization.php

# probes de diagnóstico (opcionais)
php /var/www/html/qloapps/tests/probe_cart_pricing.php
php /var/www/html/qloapps/tests/probe_matrix.php
```

A conferência pela interface é igual à **seção 9.2** (abrir
http://localhost/qloapps/ → Rooms → General Rooms → reservar datas dentro das
janelas dos planos e comparar os totais do carrinho).

### 11.9 Problemas comuns no Fedora

| Sintoma | Causa / Solução |
|---|---|
| 403 / Permission denied no site inteiro | SELinux bloqueando — re-aplique o contexto (11.4) e veja `sudo ausearch -m avc -ts recent` |
| URLs amigáveis dão 404 (só a home abre) | `AllowOverride All` não aplicado — refaça 11.5 e reinicie o httpd |
| Instalador não grava `config/settings.inc.php` | contexto SELinux sem escrita — 11.4 |
| Instalador não conecta no banco | usuário errado — no Fedora **não** é root sem senha (11.3) |
| Fatais do PHP não aparecem na tela | `display_errors` vem Off — veja `sudo tail -f /var/log/httpd/error_log` |
| Servidor trocado (nginx/php-fpm etc.) | este guia cobre httpd + mod_php; outros setups exigem ajustes próprios de fpm/SELinux |
| Erros de cache do Smarty | limpe `cache/smarty/compile` e `cache/smarty/cache` (11.6) |
