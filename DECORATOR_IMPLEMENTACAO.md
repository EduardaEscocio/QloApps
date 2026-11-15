# Implementação do padrão Decorator no cálculo de preço do QloApps

**Projeto:** Estudo de Padrões de Projeto GoF — QloApps 1.8.0.0
**Branch:** `feature/decorator-pricing`
**Data:** 2026-11-15
**Autor:** refactor aplicado pelo agente (ZCode), aprovado previamente em plano

---

## 1. Contexto

Este trabalho aplica o padrão **Decorator (GoF estrutural)** a uma parte do QloApps
**distinta** da aplicação de **Strategy** feita no fluxo de pagamentos
(`Core/Business/Payment/Strategy/` — fork da Maria Eduarda). Nenhum arquivo tocado por ela
foi modificado aqui.

**Alvo escolhido:** `HotelRoomTypeFeaturePricing::getRoomTypeTotalPrice()`
(`modules/hotelreservationsystem/classes/HotelRoomTypeFeaturePricing.php`) — método estático
de ~200 linhas que calculava o preço total de uma estadia acumulando, em blocos
sequenciais `if/else`: preço base diário → impacto do plano de feature pricing →
taxa turística → serviços de quarto → multiplicador de ocupação. É o ponto mais quente
do sistema (**37 pontos de chamada em 10 arquivos**, incluindo `Cart.php`, `PaymentModule.php`
e o webservice ARI).

## 2. O que foi alterado

| Arquivo | Alteração |
|---|---|
| `modules/hotelreservationsystem/classes/HotelRoomTypeFeaturePricing.php` | `getRoomTypeTotalPrice()` reescrito como **orquestrador**: monta o contexto, executa a cadeia Decorator por dia, dispara o hook e aplica o multiplicador. Assinatura **100% idêntica** — nenhum dos 37 pontos de chamada mudou. |
| `modules/hotelreservationsystem/define.php` | 6 `require_once` novos para as classes da cadeia. |

Métodos preservados intactos: `getRoomTypeFeaturePricesPerDay()` (adaptador que delega ao
método total), estágios **hook** (`actionRoomTypeTotalPriceModifier`) e **multiplicador de
ocupação** (permanecem inline, nas posições originais).

> **Nota:** uma cópia do método original foi mantida como
> `getRoomTypeTotalPriceLegacy()` (protected, uso exclusivo do teste de caracterização —
> ver seção 5). Decisão deliberada para manter o harness reproduzível; pode ser removida
> após a aceitação final (o harness precisaria então de valores esperados fixos).

## 3. O que foi criado

Todos os arquivos em `modules/hotelreservationsystem/classes/` (padrão do módulo:
1 classe por arquivo, nome do arquivo = nome da classe):

| Classe | Papel no padrão Decorator |
|---|---|
| `HotelRoomTypePriceContext` | Objeto de contexto/acumulador: parâmetros de entrada, estado compartilhado (preço base bruto, taxRate, moeda, quantidade) e totais correntes TI/TE. Carrega os dados base sob demanda (`initBasePriceData()`). |
| `HotelRoomTypePriceComponent` | **Componente abstrato (Decorator base)**: guarda o componente interno (`$innerComponent`), `apply()` delega ao interno e aplica `doApply()`; `getDayPrice()` é a operação decorável por dia. |
| `HotelRoomTypeBasePriceComponent` | **Componente concreto**: preço-base de um dia (preço por noite via `Product::getPriceStatic` + desconto de grupo). |
| `HotelRoomTypeFeaturePricingComponent` | **Decorador 1 (por dia)**: se existe plano para a data, aplica a matriz de impacto (percentual×diminuir/aumentar/fixar), clamp ≥ 0 e desconto de grupo com gate `id_cart`; dias sem plano delegam ao componente interno. |
| `HotelRoomTypeTourismTaxComponent` | **Decorador 2 (por estadia)**: taxa turística por quarto/idade, dividida pelo nº de quartos. |
| `HotelRoomTypeAutoServicesComponent` | **Decorador 3 (por estadia)**: serviços do carrinho (com `id_cart`+`id_room`) ou serviços auto-adicionados do room type. |

Arquivos de teste:

| Arquivo | Função |
|---|---|
| `tests/pricing_characterization.php` | Harness CLI que compara a implementação nova com a legada em 13 cenários (assert `|Δ| < 0.0001`). |
| `tests/fixtures_feature_pricing.sql` | Fixture temporária: 3 planos de feature pricing (decrease 20% / increase fixo 100 / fix 500) + seção de cleanup comentada. |

## 4. A cadeia Decorator

```
getRoomTypeTotalPrice()  — orquestrador (assinatura inalterada)
│
│  contexto = new HotelRoomTypePriceContext(...)
│
│  ── FASE POR DIA (loop da estadia) ─────────────────────────
│  HotelRoomTypeFeaturePricingComponent          (Decorador)
│      └── HotelRoomTypeBasePriceComponent       (Componente concreto)
│          → por dia: plano? matriz de impacto : preço-base + desconto de grupo
│
│  ── HOOK ── actionRoomTypeTotalPriceModifier (inline, posição original) ──
│
│  ── FASE POR ESTADIA ───────────────────────────────────────
│  HotelRoomTypeAutoServicesComponent            (Decorador)
│      └── HotelRoomTypeTourismTaxComponent      (Decorador)
│          → taxa turística primeiro, depois serviços (ordem original)
│
│  ── MULTIPLICADOR DE OCUPAÇÃO (inline, posição original) ──
└─ return ['total_price_tax_incl', 'total_price_tax_excl']
```

**Invariantes de comportamento preservados** (verificados no teste):
`use_reduc` controla simultaneamente reduções e lookup de plano; matriz sobre preço
bruto (não sobre preço com desconto de grupo); desconto de grupo por dia e nunca para
plano cart-scoped; clamp em 0; `date_from == date_to` → +1 noite; taxa turística ÷ nº de
quartos (o multiplicador vem depois); hook antes de taxa/serviços.

## 5. Como verificar

```bash
# 1. Teste de caracterização (compara nova pipeline vs. implementação legada)
cd C:\Users\mpadm\OneDrive\Documentos\estudos enzo\pds\qloapps
C:\xampp\php\php.exe tests\pricing_characterization.php
# Resultado esperado: 13/13 PASS

# 2. Fixture de planos (já aplicada; cleanup comentado dentro do arquivo)
mysql -u root qloapps < tests\fixtures_feature_pricing.sql
```

**Resultados obtidos em 2026-11-15:** 13/13 cenários idênticos (d TI = d TE = 0.0000).

## 6. Ambiente montado (Fase A)

- **XAMPP 8.2.12** (via winget): PHP 8.2.12, Apache 2.4.58, MariaDB 10.4.32 — atende
  `composer.json` (`>=8.2 <8.6`). Extensões habilitadas no `php.ini`: `gd`, `soap`, `zip`
  (as demais já ativas).
- **Banco:** `qloapps` (usuário `root`, sem senha — padrão XAMPP).
- **Servindo o clone** via junction: `C:\xampp\htdocs\qloapps` → pasta do repositório.
- **Instalação web** concluída com demo data (`install-dev` renomeado para
  `install-dev.bak` — não deletado).
- **Acessos:**
  - Front office: <http://localhost/qloapps/> (Hotel Prime, demo)
  - Back office: <http://localhost/qloapps/admin-dev/>
    - Usuário: `admin@qloapps.local` — Senha: `QloApps2026!`
- MySQL e Apache rodam como processos em segundo plano iniciados nesta sessão
  (sem serviço do Windows, pois o shell não é admin). Para subir manualmente depois:
  `C:\xampp\mysql\bin\mysqld.exe --defaults-file=C:/xampp/mysql/bin/my.ini --standalone`
  e `C:\xampp\apache\bin\httpd.exe` (ou pelo `xampp-control.exe`).

## 7. O que NÃO foi tocado

- **Trabalho da Maria Eduarda (Strategy):** `Core/Business/Payment/` (PaymentOption,
  Strategy/*), `controllers/front/PaymentProcessorController.php`,
  `themes/hotel-reservation-theme/header.tpl` e `order-payment.tpl`.
- **Os 37 pontos de chamada** do cálculo de preço — a assinatura estática foi preservada.
- **Estágios hook e multiplicador de ocupação** — permanecem inline nas posições originais.
- **`getRoomTypeFeaturePricesPerDay()`** — adaptador intacto.
