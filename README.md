# Store TI API

Backend Laravel para catálogo, clientes, vendas, checkout Pix, Mercado Pago, empresas e notificações.

## URL base e formato

Nos exemplos, substitua `http://localhost:8000` pela URL da instalação.

```text
http://localhost:8000
```

Envie e aceite JSON nas rotas comuns:

```http
Accept: application/json
Content-Type: application/json
```

Valores monetários recebidos em campos como `price` e `discount_value` estão em reais. Campos terminados em `_cents` estão em centavos.

## Autenticação

A API usa sessão e cookies, não token Bearer. O cliente HTTP deve guardar e reenviar o cookie recebido no login. Em navegadores, use `credentials: "include"`; no Axios, `withCredentials: true`.

Existem duas sessões independentes:

- Administrativa: `POST /login`, protegendo as rotas sob `/api`.
- Cliente da loja: `POST /customer/login` ou `POST /customer/register`, protegendo perfil, endereços, pedidos e criação de Pix de produto.

Os perfis administrativos são `superadmin`, `admin` e `seller`. Algumas rotas recusam o perfil `seller`, e as rotas de empresas exigem `superadmin`.

## Códigos de resposta comuns

| Código | Significado |
|---|---|
| `200` | Consulta ou alteração realizada |
| `201` | Recurso criado |
| `204` | Recurso excluído, sem body |
| `401` | Sessão ausente ou inválida |
| `403` | Sem permissão ou empresa incorreta |
| `404` | Recurso não encontrado |
| `422` | Erro de validação ou regra de negócio |

## Rotas de infraestrutura

| Método | Rota | Autenticação | Body/Parâmetros | Descrição |
|---|---|---|---|---|
| `GET` | `/up` | Não | — | Health check do Laravel |
| `GET` | `/storage/{path}` | Não | Path do arquivo | Arquivos do storage local |
| `PUT` | `/storage/{path}` | Conforme configuração | Conteúdo do arquivo | Upload do storage local do Laravel |
| `POST` | `/webhooks/mercado-pago` | Não | Payload do Mercado Pago | Atualiza pagamentos por webhook |
| `POST` | `/webhooks/asaas/{tenant}` | Token Asaas | Payload do Asaas | Atualiza pagamentos Asaas da empresa |
| `GET` | `/checkout/resultado?status=success&link={publicId}` | Não | Query `status` e `link` | Retorna os parâmetros do retorno do checkout |

O webhook deve ser configurado no Mercado Pago como:

```text
POST http://localhost:8000/webhooks/mercado-pago
```

Para o Asaas, configure a URL abaixo e use o mesmo valor de `payment_credentials.asaas.webhook_token` no token de autenticação do webhook:

```text
POST http://localhost:8000/webhooks/asaas/{tenantId}
Header: asaas-access-token: seu-token-secreto
```

### Retorno unificado de pagamento

Mercado Pago e Asaas retornam o mesmo contrato ao frontend:

```json
{
  "sale_id": "uuid-da-venda",
  "payment_id": "pay_123",
  "provider": "asaas",
  "status": "pending",
  "status_detail": "PENDING",
  "payment_method": "pix",
  "qr_code": "000201...",
  "qr_code_base64": "imagem-em-base64",
  "ticket_url": "https://...",
  "checkout_url": "https://...",
  "expires_at": "2026-07-17 23:59:59"
}
```

## Autenticação administrativa

| Método | Rota | Autenticação | Descrição |
|---|---|---|---|
| `POST` | `/login` | Visitante | Inicia a sessão administrativa |
| `POST` | `/logout` | Administrador | Encerra a sessão |
| `GET` | `/api/me` | Administrador | Retorna usuário e empresa da sessão |

### Login administrativo

`POST /login`

```json
{
  "email": "admin@example.com",
  "password": "secret123",
  "remember": true
}
```

`POST /logout` e `GET /api/me` não possuem body.

## Sessão e conta do cliente

| Método | Rota | Sessão de cliente | Body | Descrição |
|---|---|---|---|---|
| `GET` | `/auth/session` | Opcional | — | Estado resumido da sessão do cliente |
| `GET` | `/customer/session` | Opcional | — | Estado completo da sessão do cliente |
| `POST` | `/customer/register` | Não | Cadastro | Cria conta e inicia sessão |
| `POST` | `/customer/login` | Não | Credenciais | Inicia sessão na empresa indicada |
| `POST` | `/customer/logout` | Sim | — | Encerra a sessão do cliente |
| `PUT` | `/customer/profile` | Sim | Perfil | Atualiza o perfil |
| `GET` | `/customer/orders` | Sim | — | Lista pedidos do cliente |

### Cadastrar cliente

`POST /customer/register`

```json
{
  "store_slug": "minha-loja",
  "name": "Maria Silva",
  "email": "maria@example.com",
  "password": "secret123",
  "phone": "11999998888",
  "cpf": "529.982.247-25"
}
```

`phone` e `cpf` são opcionais. O CPF, quando informado, deve ser válido.

### Login do cliente

`POST /customer/login`

```json
{
  "store_slug": "minha-loja",
  "email": "maria@example.com",
  "password": "secret123"
}
```

### Atualizar perfil

`PUT /customer/profile`

```json
{
  "name": "Maria da Silva",
  "email": "maria@example.com",
  "phone": "11999998888",
  "cpf": "529.982.247-25",
  "password": "novaSenha123"
}
```

`password`, `phone` e `cpf` são opcionais.

## Endereços do cliente

| Método | Rota | Sessão de cliente | Descrição |
|---|---|---|---|
| `POST` | `/customer/addresses` | Sim | Cria endereço |
| `PUT` | `/customer/addresses/{address}` | Sim | Atualiza endereço pertencente ao cliente |
| `DELETE` | `/customer/addresses/{address}` | Sim | Exclui endereço pertencente ao cliente |

Body de criação e atualização:

```json
{
  "label": "Casa",
  "recipient_name": "Maria Silva",
  "phone": "11999998888",
  "cep": "01001-000",
  "street": "Praça da Sé",
  "number": "100",
  "complement": "Apto 10",
  "neighborhood": "Sé",
  "city": "São Paulo",
  "state": "SP",
  "default": true
}
```

São obrigatórios: `cep`, `street`, `number`, `neighborhood`, `city` e `state` com duas letras.

## Catálogo e checkout públicos

| Método | Rota | Sessão | Body/Query | Descrição |
|---|---|---|---|---|
| `GET` | `/tenant-settings/public` | Não | — | Configuração pública da empresa atual |
| `GET` | `/loja/{slug}` | Não | — | Loja e produtos ativos em JSON |
| `GET` | `/loja/{slug}/data` | Não | — | Alias compatível da rota anterior |
| `GET` | `/p/{publicId}` | Não | — | Dados públicos de um produto |
| `GET` | `/p/{publicId}/data` | Não | — | Alias compatível da rota anterior |
| `POST` | `/p/{publicId}/pix` | Cliente | Dados do checkout | Cria venda e pagamento Pix |
| `GET` | `/p/{publicId}/status?sale={salePublicId}` | Não | Query `sale` | Consulta/sincroniza pagamento da compra |
| `GET` | `/v/{salesLink}` | Não | — | Dados públicos de um link de venda |
| `GET` | `/v/{salesLink}/data` | Não | — | Alias compatível da rota anterior |
| `POST` | `/v/{salesLink}/pix` | Cliente | Dados do checkout | Cria pagamento Pix do link |
| `GET` | `/v/{salesLink}/status` | Não | — | Consulta/sincroniza pagamento do link |
| `GET` | `/v/{salesLink}/checkout` | Não | — | Redireciona para checkout do Mercado Pago |

### Pix a partir de produto

`POST /p/{publicId}/pix`

```json
{
  "name": "Maria Silva",
  "email": "maria@example.com",
  "phone": "11999998888",
  "cpf": "529.982.247-25",
  "cep": "01001-000",
  "shipping_region": "Sudeste",
  "shipping_eta": "2 a 4 dias úteis",
  "shipping_amount_cents": 1990,
  "selected_size": "M",
  "selected_color": "Preto",
  "quantity": 2,
  "customer_address_id": 10
}
```

Todos os campos são opcionais, mas `customer_address_id` é exigido quando o produto requer entrega. A rota exige sessão de cliente da mesma empresa do produto.

### Pix a partir de link de venda

`POST /v/{salesLink}/pix`

```json
{
  "name": "Maria Silva",
  "email": "maria@example.com",
  "phone": "11999998888",
  "cpf": "529.982.247-25",
  "cep": "01001-000",
  "shipping_region": "Sudeste",
  "shipping_eta": "2 a 4 dias úteis",
  "shipping_amount_cents": 1990,
  "selected_size": "M",
  "selected_color": "Preto",
  "customer_address_id": 10
}
```

Essa rota também exige sessão de cliente da mesma empresa do link. `customer_address_id` é obrigatório quando o produto requer entrega.

## Dashboard e relatórios

| Método | Rota | Autenticação | Body/Query | Descrição |
|---|---|---|---|---|
| `GET` | `/api/dashboard` | Administrador | — | Indicadores do dashboard |
| `GET` | `/api/reports` | Admin/superadmin | Query opcional | Relatório do período |

Exemplo de relatório:

```text
GET /api/reports?from=2026-07-01&to=2026-07-31
```

As datas usam o formato `YYYY-MM-DD`; `to` deve ser igual ou posterior a `from`.

## Produtos administrativos

| Método | Rota | Autenticação | Descrição |
|---|---|---|---|
| `GET` | `/api/products` | Administrador | Lista produtos da empresa selecionada |
| `POST` | `/api/products` | Admin/superadmin | Cria produto |
| `PUT/PATCH` | `/api/products/{product}` | Admin/superadmin | Atualiza produto inteiro |
| `DELETE` | `/api/products/{product}` | Admin/superadmin | Exclui produto |

Body de criação/atualização:

```json
{
  "name": "Camiseta Store TI",
  "sku": "CAM-001",
  "type": "physical",
  "description": "Camiseta de algodão",
  "image_url": "https://example.com/camiseta.jpg",
  "gallery_urls": ["https://example.com/camiseta-2.jpg"],
  "options": {
    "sizes": ["P", "M", "G"],
    "colors": ["Preto", "Branco"],
    "variants": [
      {
        "size": "M",
        "color": "Preto",
        "price_cents": 8990,
        "image_url": "https://example.com/camiseta-preta.jpg"
      }
    ]
  },
  "requires_shipping": true,
  "shipping_weight_grams": 300,
  "price": 89.9,
  "discount_type": "percent",
  "discount_value": 10,
  "track_stock": true,
  "stock": 25,
  "billing_cycle": "one_time",
  "active": true
}
```

Valores aceitos:

- `type`: `physical`, `internet_plan`, `service`, `subscription`, `other`.
- `discount_type`: `none`, `fixed`, `percent`.
- `billing_cycle`: `none`, `monthly`, `quarterly`, `semiannual`, `annual`, `one_time`.

## Links de venda

| Método | Rota | Autenticação | Descrição |
|---|---|---|---|
| `GET` | `/api/sales-links` | Administrador | Lista vendas da empresa |
| `POST` | `/api/sales-links` | Administrador | Cria link de venda |
| `PATCH` | `/api/sales-links/{salesLink}` | Administrador | Atualiza status e entrega |
| `DELETE` | `/api/sales-links/{salesLink}` | Administrador | Exclui link |
| `POST` | `/api/sales-links/{salesLink}/refresh` | Administrador | Sincroniza pagamento com Mercado Pago |

Criar link:

```json
{
  "product_id": 1,
  "title": "Pedido da Maria",
  "customer_email": "maria@example.com",
  "quantity": 2,
  "discount_type": "fixed",
  "discount_value": 15.5,
  "expires_at": "2026-08-31T23:59:59-03:00"
}
```

Atualizar venda/entrega:

```json
{
  "status": "paid",
  "delivery_status": "shipped",
  "tracking_code": "BR123456789",
  "tracking_url": "https://example.com/rastreio/BR123456789",
  "delivery_note": "Postado nos Correios"
}
```

`status`: `draft`, `ready`, `pending`, `paid`, `cancelled`. `delivery_status`: `waiting_payment`, `preparing`, `shipped`, `delivered`, `cancelled`.

## Clientes administrativos

| Método | Rota | Autenticação | Descrição |
|---|---|---|---|
| `GET` | `/api/customers` | Admin/superadmin | Lista clientes da empresa |
| `PUT/PATCH` | `/api/customers/{customer}` | Admin/superadmin | Atualiza cliente |
| `DELETE` | `/api/customers/{customer}` | Admin/superadmin | Exclui cliente |

```json
{
  "name": "Maria Silva",
  "email": "maria@example.com",
  "phone": "11999998888",
  "cpf": "529.982.247-25",
  "active": true
}
```

## Usuários administrativos

| Método | Rota | Autenticação | Descrição |
|---|---|---|---|
| `GET` | `/api/users` | Admin/superadmin | Lista usuários permitidos |
| `POST` | `/api/users` | Admin/superadmin | Cria usuário |
| `PUT/PATCH` | `/api/users/{user}` | Admin/superadmin | Atualiza usuário inteiro |
| `DELETE` | `/api/users/{user}` | Admin/superadmin | Exclui usuário |

Criação:

```json
{
  "name": "João Operador",
  "email": "joao@example.com",
  "password": "secret123",
  "tenant_setting_id": 1,
  "role": "seller",
  "active": true
}
```

Na atualização, envie os mesmos campos. `password` pode ser omitido. Somente o superadmin pode criar outro `superadmin`; administradores de empresa ficam limitados à própria empresa.

## Empresas e configurações da loja

| Método | Rota | Autenticação | Descrição |
|---|---|---|---|
| `GET` | `/api/tenant-settings` | Admin/superadmin | Configuração da empresa ativa |
| `PUT` | `/api/tenant-settings` | Admin/superadmin | Atualiza a empresa ativa |
| `GET` | `/api/companies` | Superadmin | Lista empresas |
| `POST` | `/api/companies` | Superadmin | Cria empresa |
| `PUT` | `/api/companies/{tenant}` | Superadmin | Atualiza empresa |
| `PATCH` | `/api/companies/{tenant}/status` | Superadmin | Ativa ou inativa empresa |
| `DELETE` | `/api/companies/{tenant}` | Superadmin | Exclui empresa |
| `POST` | `/api/companies/{tenant}/activate` | Superadmin | Seleciona empresa na sessão |
| `POST` | `/api/companies/deactivate` | Superadmin | Volta à visão da plataforma |

Body resumido para criar ou atualizar empresa:

```json
{
  "name": "Minha Loja",
  "active": true,
  "store_slug": "minha-loja",
  "store_theme": "default",
  "store_title": "Minha Loja",
  "store_subtitle": "Tecnologia para você",
  "store_banner_label": "Ofertas",
  "store_banner_image_url": "https://example.com/banner.jpg",
  "store_featured_image_url": "https://example.com/destaque.jpg",
  "store_featured_label": "Destaque",
  "store_featured_title": "Produto da semana",
  "store_featured_subtitle": "Confira nossa oferta",
  "store_featured_cta": "Comprar",
  "store_secure_image_url": "https://example.com/seguro.jpg",
  "store_secure_label": "Compra segura",
  "store_secure_title": "Seus dados protegidos",
  "store_secure_subtitle": "Pagamento processado com segurança",
  "store_secure_cta": "Saiba mais",
  "store_shipping_regions": [
    {
      "region": "Sudeste",
      "cep_prefix": "0-3",
      "price": 19.9,
      "eta": "2 a 4 dias úteis"
    }
  ],
  "document": "12.345.678/0001-90",
  "support_phone": "11999998888",
  "support_email": "suporte@example.com",
  "admin_primary_color": "#111C22",
  "admin_accent_color": "#0F766E",
  "checkout_primary_color": "#3B82F6",
  "checkout_button_color": "#43C97B",
  "active_payment_provider": "mercado_pago",
  "payment_providers": {
    "mercado_pago": {"enabled": true}
  },
  "payment_credentials": {
    "mercado_pago": {
      "access_token": "APP_USR-...",
      "public_key": "APP_USR-..."
    },
    "asaas": {
      "api_key": "$aact_hmlg_...",
      "webhook_token": "um-token-secreto-forte"
    }
  }
}
```

Chaves Asaas iniciadas por `$aact_hmlg_` usam o Sandbox; chaves `$aact_prod_` usam Produção. O `webhook_token` é opcional para considerar o gateway configurado, mas obrigatório para aceitar notificações no endpoint do Asaas.

Alterar somente o status:

```json
{
  "active": false
}
```

As rotas `activate`, `deactivate` e `DELETE` não recebem body.

## Temas da loja

| Método | Rota | Autenticação | Descrição |
|---|---|---|---|
| `GET` | `/api/store-themes` | Administrador | Lista temas da empresa |
| `POST` | `/api/store-themes` | Administrador | Cria tema |
| `PUT/PATCH` | `/api/store-themes/{store_theme}` | Administrador | Atualiza tema |
| `DELETE` | `/api/store-themes/{store_theme}` | Administrador | Exclui tema |

```json
{
  "name": "Tema Azul",
  "slug": "tema-azul",
  "primary_color": "#2563EB",
  "accent_color": "#F59E0B",
  "background_color": "#F8FAFC",
  "banner_label": "Promoção",
  "banner_image_url": "https://example.com/banner.jpg",
  "featured_image_url": "https://example.com/featured.jpg",
  "featured_title": "Destaque",
  "featured_subtitle": "Oferta especial",
  "active": true
}
```

## Upload de mídia

| Método | Rota | Autenticação | Content-Type | Descrição |
|---|---|---|---|---|
| `POST` | `/api/storefront-media` | Administrador | `multipart/form-data` | Envia imagem de até 4 MB |

Exemplo com cURL:

```bash
curl -X POST http://localhost:8000/api/storefront-media \
  -H "Accept: application/json" \
  -b cookies.txt \
  -F "image=@banner.jpg"
```

## Mercado Pago

| Método | Rota | Autenticação | Descrição |
|---|---|---|---|
| `GET` | `/api/payment-settings` | Admin/superadmin | Consulta configuração sem expor o token |
| `PUT` | `/api/payment-settings` | Admin/superadmin | Atualiza credenciais |

```json
{
  "access_token": "APP_USR-...",
  "public_key": "APP_USR-...",
  "sandbox": false,
  "statement_descriptor": "STORE TI"
}
```

`access_token` é opcional na atualização para permitir manter o token existente. `statement_descriptor` aceita até 22 caracteres.

## Notificações Evolution API

| Método | Rota | Autenticação | Descrição |
|---|---|---|---|
| `GET` | `/api/notification-settings` | Admin/superadmin | Configurações, contatos e logs recentes |
| `PUT` | `/api/notification-settings` | Admin/superadmin | Atualiza configurações e contatos |
| `POST` | `/api/notification-settings/test` | Admin/superadmin | Envia mensagem de teste |

Atualização:

```json
{
  "enabled": true,
  "provider_enabled": true,
  "base_url": "https://evolution.example.com",
  "instance": "store-ti",
  "api_key": "minha-chave",
  "dynamic_customer_enabled": true,
  "notify_sale_created": true,
  "notify_payment_approved": true,
  "sale_created_message": "Nova venda: {produto}",
  "payment_approved_message": "Pagamento aprovado: {produto}",
  "contacts": [
    {
      "name": "Financeiro",
      "phone": "5511999998888",
      "active": true
    }
  ]
}
```

Ao atualizar um contato existente, inclua seu `id`. Credenciais globais do provedor só podem ser alteradas pelo superadmin na visão da plataforma.

Teste:

```json
{
  "phone": "5511999998888",
  "name": "Renan"
}
```

## Exemplo completo com cURL e cookie

```bash
# Entrar e salvar o cookie
curl -X POST http://localhost:8000/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -c cookies.txt \
  -d '{"email":"admin@example.com","password":"secret123"}'

# Consumir uma rota autenticada
curl http://localhost:8000/api/products \
  -H "Accept: application/json" \
  -b cookies.txt
```

## Fonte das rotas

Todas as rotas da aplicação estão centralizadas em [`routes/api.php`](routes/api.php). Para conferir a lista gerada pelo Laravel:

```bash
php artisan route:list
```
