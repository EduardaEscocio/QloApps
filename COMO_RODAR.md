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

**Se aparecer "Erro na validação do carrinho":** o controller exige que o carrinho
tenha endereço (`id_address_invoice != 0`). Isso é normal quando o fluxo pulou a
etapa de endereço — complete o endereço do cliente no checkout completo
(`index.php?controller=order`) ou cadastre pelo back office.

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
| "Erro na validação do carrinho" ao pagar | carrinho sem endereço — complete o endereço do cliente (seção 8) |
| Preços sem efeito dos planos | fixture não aplicado (9.1) ou datas fora das janelas — rode o fixture de novo (ele é idempotente e recalcula as janelas pelo dia atual) |
| Apache/MySQL não sobem pelo painel | rode `xampp-control.exe` como administrador, ou inicie os binários manualmente (passo 3) |
