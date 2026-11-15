# Fluxos GoF do trabalho — Strategy (pagamento) e Decorator (preço)

Este documento explica **os dois fluxos** implementados no QloApps, como cada um
funciona por dentro, e o roteiro para demonstrá-los. Para subir o ambiente de
zero, siga o **COMO_RODAR.md** (seções 1 a 7) antes deste.

| Trabalho | Padrão GoF | Área do código | Autoria |
|---|---|---|---|
| Fluxo do **pagamento** | **Strategy** (comportamental) | `Core/Business/Payment/` + `controllers/front/` | Maria Eduarda |
| Fluxo do **cálculo de preço** | **Decorator** (estrutural) | `modules/hotelreservationsystem/classes/` | Enzo (aplicado pelo agente) |

Os dois fluxos convivem no mesmo site e **não se sobrepõem**: um decide *como
cobrar*, o outro decide *quanto cobrar*.

---

## 1. Fluxo da Maria Eduarda — Strategy no pagamento

### 1.1 O problema que o padrão resolve

A etapa de pagamento precisava suportar formas de pagamento distintas (PIX,
cartão via Stripe, recebimento no balcão) **sem espalhar `if/else` por todo o
código**. O Strategy encapsula cada forma de pagamento em uma classe com a
**mesma interface**, permitindo trocar o algoritmo em tempo de execução conforme
a escolha do usuário.

### 1.2 Peças do padrão (onde está cada papel)

| Papel no Strategy | Classe / arquivo |
|---|---|
| **Strategy (interface comum)** | `Core/Business/Payment/Strategy/PaymentStrategy.php` — método `processPayment($cart, $amount)` |
| **Estratégias concretas** | `Strategy/PixPaymentStrategy.php`, `Strategy/StripePaymentStrategy.php`, `Strategy/OfflinePaymentStrategy.php` |
| **Fábrica de estratégias** (apoio) | `Strategy/PaymentStrategyFactory.php` — `createStrategy($moduleName)` mapeia o nome do módulo → objeto |
| **Contexto** (quem usa a estratégia) | `Core/Business/Payment/PaymentOption.php` — guarda a estratégia (`setModuleName`) e a executa (`processOptionPayment`) |
| **Entrada HTTP** | `controllers/front/PaymentProcessorController.php` — recebe o AJAX da tela |
| **Tela** | `themes/hotel-reservation-theme/order-payment.tpl` — radios + botão + JS do `fetch` |

### 1.3 O fluxo, passo a passo

```
[1] Tela de pagamento (order-payment.tpl)
    usuário seleciona um radio (valor = nome do módulo, ex.: "pix")
    e clica em "Confirmar Pedido"
        │
        │  fetch POST index.php?controller=PaymentProcessor
        │        &ajax=true&payment_module=pix
        ▼
[2] PaymentProcessorController::postProcess()
    valida carrinho (id, id_customer, id_address_invoice)
    calcula o valor: $cart->getOrderTotal(true, Cart::BOTH)
        │
        ▼
[3] PaymentOption::setModuleName("pix")          ← CONTEXTO
    └─ PaymentStrategyFactory::createStrategy("pix")   ← FÁBRICA
           return new PixPaymentStrategy();
        │
        ▼
[4] PaymentOption::processOptionPayment($cart, $amount)
    └─ $this->paymentStrategy->processPayment($cart, $amount)
       ▲──────────────── CHAMADA POLIMÓRFICA — o coração do Strategy ────────┐
        │                                                                    │
        ▼                                                                    │
    O PHP escolhe o processPayment() da classe concreta em runtime:          │
    PixPaymentStrategy     → gera "QR Code" (stub)                           │
    StripePaymentStrategy  → "processado via Stripe" (stub)                  │
    OfflinePaymentStrategy → "aguardando confirmação no balcão" (stub)       │
        │
        ▼
[5] Resposta JSON para a tela
    {"success":true,"data":{...resultado da estratégia...}}
    JS da tela mostra o alert correspondente
```

Módulo **sem estratégia implementada**: a factory retorna `null`,
`processOptionPayment()` lança `Exception`, o controller captura e devolve
`{"success":false,"message":"Estratégia de pagamento não implementada para o
módulo: X"}` — o fluxo nunca quebra com fatal.

### 1.4 Roteiro de demonstração (interface)

1. Com o site no ar (COMO_RODAR.md), adicione um quarto ao carrinho e avance
   até a etapa **Payment Information**.
2. Deve aparecer **"Escolha a forma de pagamento:"** com os 3 radios — essa
   tela é o template modificado dela.
3. Selecione **PIX** → **Confirmar Pedido** → alert:
   *"Pagamento PIX gerado! QR Code: 00020101021126580014br.gov.bcb.pix..."*
4. Selecione **Cartão de Crédito** → **Confirmar Pedido** → alert
   *"Sucesso: Pagamento processado via Stripe!"*

Respostas reais capturadas do endpoint (evidência de que cada estratégia roda):

| `payment_module` | Resposta do endpoint |
|---|---|
| `pix` | `{"success":true,"data":{"status":"pending","message":"QR Code PIX gerado","qr_code":"00020101021126580014br.gov.bcb.pix..."}}` |
| `stripe` | `{"success":true,"data":{"status":"success","message":"Pagamento processado via Stripe","transaction_id":"ch_123456789"}}` |
| `offline` | `{"success":true,"data":{"status":"success","message":"Pagamento offline registrado. Aguardando confirmação no balcão."}}` |
| `qloduitkupayment` | `{"success":false,"message":"Estratégia de pagamento não implementada para o módulo: qloduitkupayment"}` |

**Observação didática:** as 3 estratégias são *stubs* (retornam respostas fixas)
— o objetivo do trabalho é demonstrar o **padrão** (interface única, seleção em
runtime, chamada polimórfica), não integração real com os gateways. Por isso
nenhum pedido é gravado no banco.

---

## 2. Fluxo do Enzo — Decorator no cálculo de preço

### 2.1 O problema que o padrão resolve

O preço total de uma estadia era calculado por **um único método estático de
~200 linhas** (`HotelRoomTypeFeaturePricing::getRoomTypeTotalPrice`) que
acumulava, em blocos `if/else` sequenciais: preço-base diário → plano de feature
pricing → taxa turística → serviços do quarto → multiplicador de ocupação.
Adicionar um componente novo significava editar o monólito. O **Decorator**
transforma cada componente em uma classe que **envolve** as demais, permitindo
empilhar comportamentos sem alterar os existentes.

### 2.2 Peças do padrão (onde está cada papel)

| Papel no Decorator | Classe / arquivo |
|---|---|
| **Contexto/acumulador** (apoio) | `HotelRoomTypePriceContext` — parâmetros, preços-base compartilhados e totais TI/TE |
| **Decorator abstrato** | `HotelRoomTypePriceComponent` — guarda o componente interno (`$innerComponent`), delega `apply()`/`getDayPrice()` |
| **Componente concreto** (raiz da cadeia) | `HotelRoomTypeBasePriceComponent` — preço-base da noite + desconto de grupo |
| **Decorador 1 (por dia)** | `HotelRoomTypeFeaturePricingComponent` — impacto do plano (percentual/fixo × diminuir/aumentar/fixar) |
| **Decorador 2 (por estadia)** | `HotelRoomTypeTourismTaxComponent` — taxa turística por quarto/idade |
| **Decorador 3 (por estadia)** | `HotelRoomTypeAutoServicesComponent` — serviços cobrados junto com o quarto |
| **Orquestrador** (mantém a API pública) | `HotelRoomTypeFeaturePricing::getRoomTypeTotalPrice()` — monta as cadeias; assinatura estática **inalterada** (os 37 pontos de chamada não mudaram) |

### 2.3 O fluxo, passo a passo

```
[1] Qualquer parte do sistema chama (como sempre chamou):
    HotelRoomTypeFeaturePricing::getRoomTypeTotalPrice($id_product, $date_from, ...)
        │
        ▼
[2] Orquestrador cria o contexto (acumulador TI/TE) e inicializa os dados
    compartilhados: preços-base brutos (Product::getPriceStatic), grupo do
    cliente, taxa de imposto, moeda, quantidade de quartos
        │
        ▼
[3] FASE POR DIA — para cada noite da estadia:
        │
        │  cadeia: FeaturePricingComponent ──envolve──▶ BasePriceComponent
        ▼
    getDayPrice(contexto, data):
    ├─ existe plano de feature pricing para a data (e use_reduc=1)?
    │   SIM → matriz de impacto sobre o preço-base bruto
    │         (percentual|fixo × diminuir|aumentar|fixar) + clamp ≥ 0
    │         + desconto de grupo (exceto plano cart-scoped)
    │   NÃO → delega ao componente interno: preço-base + desconto de grupo
    └─ acumulador soma TI/TE da noite
        │
        ▼
[4] HOOK actionRoomTypeTotalPriceModifier (ponto de extensão original,
    mantido na mesma posição)
        │
        ▼
[5] FASE POR ESTADIA — cadeia: AutoServicesComponent ──envolve──▶ TourismTaxComponent
    (a ordem de aplicação preserva o original: taxa turística primeiro,
    serviços depois)
        │
        ▼
[6] Multiplicador de ocupação (quantidade de quartos) — mantido inline
        │
        ▼
[7] return {total_price_tax_incl, total_price_tax_excl}
```

**Invariantes de comportamento preservados** (verificados por teste): o
`use_reduc` controla simultaneamente as reduções de preço e o lookup de plano; a
matriz de impacto incide sobre o preço **bruto**; o desconto de grupo é aplicado
por dia; data de check-in igual à de check-out vira 1 noite; o hook dispara
antes de taxa/serviços.

### 2.4 Roteiro de demonstração

**Preparação (uma vez):** aplique o fixture com 3 planos de teste —
`sudo mysql qloapps < tests/fixtures_feature_pricing.sql` (Windows:
`C:\xampp\mysql\bin\mysql.exe -u root qloapps < tests\fixtures_feature_pricing.sql`).
Ele cria planos com janelas de datas relativas ao dia atual (idempotente):

| Plano | Impacto | Janela |
|---|---|---|
| Test Decrease 20% | −20% por noite | hoje+10 a hoje+20 |
| Test Increase Fixed 100 | +100 por noite | hoje+25 a hoje+35 |
| Test Fix 500 | fixa o preço em 500 | hoje+40 a hoje+50 |

**Na interface:**

1. Abra a página do quarto **General Rooms** (room type 1, criado pela demo data).
2. Reserve com check-in/check-out dentro da janela do plano Decrease 20%
   (ex.: hoje+11 até hoje+14) e 1 quarto, 2 adultos.
3. No carrinho, o **Total Rooms price** mostra o preço com **−20% por noite**
   (valor original riscado acima dele) — o plano aplicado pelo Decorador 1.
4. Troque as datas para as janelas dos outros planos: o total sobe +100/noite
   (Decorador aplica aumento) ou fixa em 500 — o **mesmo código**, mudando só os
   dados do plano.

**Prova de equivalência (linha de comando):**

```bash
php tests/pricing_characterization.php
# → 13/13 cenários idênticos entre a pipeline Decorator e a implementação
#   original (cópia legada mantida temporariamente como referência)
```

Os cenários cobrem: estadias com/sem plano, os 3 tipos de impacto, ocupação de
vários quartos, taxa turística, serviços no carrinho, `use_reduc=0` e datas
iguais. Os `tests/probe_*.php` documentam o diagnóstico de um bug real que foi
encontrado e corrigido durante os testes (inicialização do grupo do cliente
antes do primeiro lookup de plano).

---

## 3. Como os dois padrões se complementam

| | **Strategy (dela)** | **Decorator (meu)** |
|---|---|---|
| Categoria GoF | Comportamental | Estrutural |
| Pergunta que responde | *Qual algoritmo usar?* (escolho um) | *Quais camadas aplicar?* (componho várias) |
| Relação entre os objetos | Estratégias **alternativas** entre si — só uma roda por pagamento | Decoradores **aditivos** — todos rodam, em camadas |
| Momento da escolha | Em runtime, pela escolha do usuário (radio) | Em runtime, pela montagem da cadeia no orquestrador |
| Extensão típica | Nova forma de pagamento = nova classe + caso na factory | Novo componente de preço = nova classe + um `new` a mais na cadeia |

No site os dois se encontram sem conflito: quando o usuário confirma o
pagamento, o **valor exibido e cobrado** foi calculado pela cadeia Decorator, e
a **execução do pagamento** passa pela Strategy escolhida.
