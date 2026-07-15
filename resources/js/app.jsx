import React, { useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';
import {
    Activity,
    BadgePercent,
    BarChart3,
    Bell,
    Box,
    CheckCircle2,
    CircleAlert,
    Copy,
    CreditCard,
    FileText,
    ExternalLink,
    Users,
    Link as LinkIcon,
    PackagePlus,
    Plus,
    RefreshCcw,
    Search,
    Send,
    Settings,
    ShoppingCart,
    Pencil,
    Trash2,
    X,
} from 'lucide-react';
import './bootstrap';

const money = (cents = 0) =>
    new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(cents / 100);

const formatDate = (value) => value
    ? new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value))
    : 'Sem data';

const formatShortDate = (value) => value
    ? new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: '2-digit' }).format(new Date(`${value}T00:00:00`))
    : '';

const dateInputValue = (date) => date.toISOString().slice(0, 10);

const formatCpf = (value = '') => {
    const digits = String(value || '').replace(/\D/g, '');

    if (digits.length !== 11) {
        return value || 'CPF nao informado';
    }

    return digits.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
};

const statusLabel = {
    draft: 'Configurar MP',
    ready: 'Pronto',
    pending: 'Em andamento',
    paid: 'Pago',
    cancelled: 'Cancelado',
};

const productTypeLabel = {
    physical: 'Produto fisico',
    internet_plan: 'Plano de internet',
    service: 'Servico',
    subscription: 'Assinatura',
    other: 'Outro',
};

const billingCycleLabel = {
    none: 'Sem recorrencia',
    one_time: 'Pagamento unico',
    monthly: 'Mensal',
    quarterly: 'Trimestral',
    semiannual: 'Semestral',
    annual: 'Anual',
};

const paymentStatusLabel = {
    approved: 'Pago',
    pending: 'Aguardando pagamento',
    in_process: 'Pagamento em analise',
    rejected: 'Pagamento recusado',
    cancelled: 'Pagamento cancelado',
};

const deliveryStatusLabel = {
    waiting_payment: 'Aguardando pagamento',
    preparing: 'Preparando envio',
    shipped: 'Enviado',
    delivered: 'Entregue',
    cancelled: 'Cancelado',
};

const storeThemeOptions = [
    ['default', 'Padrao profissional'],
    ['fathers_day', 'Dia dos Pais'],
    ['black_friday', 'Black Friday'],
    ['christmas', 'Natal'],
    ['clean', 'Clean'],
];

const slugify = (value = '') => String(value)
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

const parseList = (value = '') => String(value)
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean);

const listToText = (value = []) => Array.isArray(value) ? value.join(', ') : '';

const variantsToText = (variants = []) => Array.isArray(variants)
    ? variants.map((variant) => [
        variant.size || '',
        variant.color || '',
        variant.price_cents ? (Number(variant.price_cents) / 100).toFixed(2) : '',
        variant.image_url || '',
    ].join(' | ')).join('\n')
    : '';

const parseVariantsText = (value = '') => String(value)
    .split('\n')
    .map((line) => line.trim())
    .filter(Boolean)
    .map((line) => {
        const [size = '', color = '', price = '', imageUrl = ''] = line.split('|').map((item) => item.trim());

        return {
            size,
            color,
            price_cents: price ? Math.round(Number(String(price).replace(',', '.')) * 100) : null,
            image_url: imageUrl,
        };
    });

const productVariants = (product) => product?.options?.variants || [];

const findProductVariant = (product, size, color) => productVariants(product).find((variant) => (variant.size || '') === (size || '') && (variant.color || '') === (color || ''))
    || productVariants(product).find((variant) => (variant.size || '') === (size || '') && !variant.color)
    || productVariants(product).find((variant) => !variant.size && (variant.color || '') === (color || ''))
    || null;

const productDisplayPrice = (product, size = '', color = '') => {
    const variant = findProductVariant(product, size, color);

    return variant?.price_cents ?? product?.final_amount_cents ?? product?.price_cents ?? 0;
};

const defaultShippingRegions = [
    { region: 'Retirada / Digital', cep_prefix: '', price: '0', eta: 'Imediato' },
    { region: 'Entrega local', cep_prefix: '81', price: '15', eta: '1 a 2 dias uteis' },
];

function Field({ label, children }) {
    return (
        <label className="field">
            <span>{label}</span>
            {children}
        </label>
    );
}

function Loader({ label = 'Carregando...' }) {
    return (
        <div className="loader-inline" role="status" aria-live="polite">
            <span />
            <strong>{label}</strong>
        </div>
    );
}

function App() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const isLoginPage = window.location.pathname === '/login';
    const isCheckoutResultPage = window.location.pathname === '/checkout/resultado';
    const isPublicSalesPage = /^\/v\/[^/]+$/.test(window.location.pathname);
    const isPublicProductPage = /^\/p\/[^/]+$/.test(window.location.pathname);
    const isPublicCustomerLoginPage = /^\/loja\/[^/]+\/entrar$/.test(window.location.pathname);
    const isPublicCustomerRegisterPage = /^\/loja\/[^/]+\/criar-conta$/.test(window.location.pathname);
    const isPublicStoreSearchPage = /^\/loja\/[^/]+\/buscar$/.test(window.location.pathname);
    const isPublicStorePage = /^\/loja\/[^/]+$/.test(window.location.pathname);
    const [loginForm, setLoginForm] = useState({ email: '', password: '', remember: true });
    const [authenticating, setAuthenticating] = useState(false);
    const [products, setProducts] = useState([]);
    const [links, setLinks] = useState([]);
    const [currentUser, setCurrentUser] = useState(null);
    const [users, setUsers] = useState([]);
    const [customers, setCustomers] = useState([]);
    const [companies, setCompanies] = useState([]);
    const [stats, setStats] = useState({});
    const [tenant, setTenant] = useState(null);
    const [storeThemes, setStoreThemes] = useState([]);
    const [tenantForm, setTenantForm] = useState({
        name: 'Store TI',
        active: true,
        document: '',
        support_phone: '',
        support_email: '',
        store_slug: 'store-ti',
        store_theme: 'default',
        store_title: 'Store TI',
        store_subtitle: 'Conheca nossas ofertas e contrate online com seguranca.',
        store_banner_label: 'Ofertas em destaque',
        store_banner_image_url: '',
        store_featured_image_url: '',
        store_featured_label: 'Ofertas do dia',
        store_featured_title: 'Ate 20% OFF',
        store_featured_subtitle: 'Em itens selecionados',
        store_featured_cta: 'Ver',
        store_secure_image_url: '',
        store_secure_label: 'Protecao',
        store_secure_title: 'Compra segura',
        store_secure_subtitle: 'Pix e acompanhamento pelo painel',
        store_secure_cta: 'Ver',
        store_shipping_regions: defaultShippingRegions,
        admin_primary_color: '#111c22',
        admin_accent_color: '#0f766e',
        checkout_primary_color: '#3b82f6',
        checkout_button_color: '#43c97b',
        active_payment_provider: 'mercado_pago',
        payment_providers: {},
        payment_credentials: {},
    });
    const [reports, setReports] = useState(null);
    const [reportPeriod, setReportPeriod] = useState(() => {
        const to = new Date();
        const from = new Date();
        from.setDate(to.getDate() - 29);

        return {
            from: dateInputValue(from),
            to: dateInputValue(to),
        };
    });
    const [settings, setSettings] = useState({ sandbox: true, statement_descriptor: 'STORE TI', configured: false });
    const [settingsForm, setSettingsForm] = useState({
        access_token: '',
        public_key: '',
        sandbox: true,
        statement_descriptor: 'STORE TI',
    });
    const [notificationSettings, setNotificationSettings] = useState({ configured: false, enabled: false });
    const [notificationForm, setNotificationForm] = useState({
        enabled: false,
        provider_enabled: false,
        base_url: '',
        instance: '',
        api_key: '',
        dynamic_customer_enabled: false,
        notify_sale_created: true,
        notify_payment_approved: true,
        sale_created_message: '',
        payment_approved_message: '',
        contacts: [],
    });
    const [notificationLogs, setNotificationLogs] = useState([]);
    const [savingNotifications, setSavingNotifications] = useState(false);
    const [testingNotifications, setTestingNotifications] = useState(false);
    const [loading, setLoading] = useState(true);
    const [savingProduct, setSavingProduct] = useState(false);
    const [savingLink, setSavingLink] = useState(false);
    const [savingSettings, setSavingSettings] = useState(false);
    const [savingTenant, setSavingTenant] = useState(false);
    const [savingUser, setSavingUser] = useState(false);
    const [savingCompany, setSavingCompany] = useState(false);
    const [notice, setNotice] = useState('');
    const [activeSection, setActiveSection] = useState('dashboard');
    const [saleStatusFilter, setSaleStatusFilter] = useState('all');
    const [saleSearch, setSaleSearch] = useState('');
    const [editingProductId, setEditingProductId] = useState(null);
    const [productFormOpen, setProductFormOpen] = useState(false);
    const [editingUserId, setEditingUserId] = useState(null);
    const [userFormOpen, setUserFormOpen] = useState(false);
    const [editingCompanyId, setEditingCompanyId] = useState(null);
    const [companyFormOpen, setCompanyFormOpen] = useState(false);
    const [settingsFormOpen, setSettingsFormOpen] = useState(false);
    const [notificationsFormOpen, setNotificationsFormOpen] = useState(false);
    const [userForm, setUserForm] = useState({
        name: '',
        email: '',
        password: '',
        tenant_setting_id: '',
        role: 'admin',
        active: true,
    });
    const [companyForm, setCompanyForm] = useState({
        name: '',
        active: true,
        document: '',
        support_phone: '',
        support_email: '',
        store_slug: '',
        store_theme: 'default',
        store_title: '',
        store_subtitle: '',
        store_banner_label: '',
        store_banner_image_url: '',
        store_featured_image_url: '',
        store_featured_label: 'Ofertas do dia',
        store_featured_title: 'Ate 20% OFF',
        store_featured_subtitle: 'Em itens selecionados',
        store_featured_cta: 'Ver',
        store_secure_image_url: '',
        store_secure_label: 'Protecao',
        store_secure_title: 'Compra segura',
        store_secure_subtitle: 'Pix e acompanhamento pelo painel',
        store_secure_cta: 'Ver',
        store_shipping_regions: defaultShippingRegions,
        admin_primary_color: '#111c22',
        admin_accent_color: '#0f766e',
        checkout_primary_color: '#3b82f6',
        checkout_button_color: '#43c97b',
        active_payment_provider: 'mercado_pago',
        payment_providers: {},
        payment_credentials: {},
    });
    const [themeForm, setThemeForm] = useState({
        id: null,
        name: '',
        slug: '',
        primary_color: '#3b82f6',
        accent_color: '#43c97b',
        background_color: '#eef2f4',
        banner_label: '',
        banner_image_url: '',
        featured_image_url: '',
        featured_title: '',
        featured_subtitle: '',
        active: true,
    });
    const [productForm, setProductForm] = useState({
        name: '',
        sku: '',
        type: 'internet_plan',
        description: '',
        image_url: '',
        gallery_urls_text: '',
        sizes_text: '',
        colors_text: '',
        variants_text: '',
        requires_shipping: false,
        shipping_weight_grams: '',
        price: '',
        discount_type: 'none',
        discount_value: '',
        track_stock: false,
        stock: '',
        billing_cycle: 'monthly',
        active: true,
    });
    const [linkForm, setLinkForm] = useState({
        product_id: '',
        title: '',
        customer_email: '',
        quantity: 1,
        discount_type: 'none',
        discount_value: '',
        expires_at: '',
    });

    const selectedProduct = useMemo(
        () => products.find((product) => String(product.id) === String(linkForm.product_id)),
        [products, linkForm.product_id],
    );

    const projectedLinkTotal = useMemo(() => {
        if (!selectedProduct) return 0;

        const quantity = Number(linkForm.quantity || 1);
        const base = selectedProduct.price_cents * quantity;
        const discountValue = Number(linkForm.discount_value || 0);

        if (linkForm.discount_type === 'fixed') {
            return Math.max(base - Math.round(discountValue * 100), 0);
        }

        if (linkForm.discount_type === 'percent') {
            return Math.max(base - Math.round(base * Math.min(discountValue, 100) / 100), 0);
        }

        return base;
    }, [selectedProduct, linkForm]);

    const salesByStatus = useMemo(() => ({
        draft: links.filter((link) => link.status === 'draft').length,
        ready: links.filter((link) => link.status === 'ready').length,
        pending: links.filter((link) => link.status === 'pending').length,
        paid: links.filter((link) => link.status === 'paid').length,
        cancelled: links.filter((link) => link.status === 'cancelled').length,
    }), [links]);

    const filteredLinks = useMemo(() => {
        const search = saleSearch.trim().toLowerCase();

        return links.filter((link) => {
            const payment = link.payments?.[0];
            const effectiveStatus = payment?.status === 'approved'
                ? 'paid'
                : ['pending', 'in_process'].includes(payment?.status)
                    ? 'pending'
                    : ['rejected', 'cancelled', 'refunded', 'charged_back'].includes(payment?.status)
                        ? 'cancelled'
                        : link.status;

            if (saleStatusFilter !== 'all' && effectiveStatus !== saleStatusFilter) {
                return false;
            }

            if (!search) {
                return true;
            }

            const customer = link.customer || {};
            return [
                link.title,
                link.product?.name,
                customer.name,
                customer.email,
                customer.phone,
                customer.cpf,
                link.public_id,
            ].some((value) => String(value || '').toLowerCase().includes(search));
        });
    }, [links, saleSearch, saleStatusFilter]);

    const productOverview = useMemo(() => ({
        active: products.filter((product) => product.active).length,
        internetPlans: products.filter((product) => product.type === 'internet_plan').length,
        withoutStockControl: products.filter((product) => !product.track_stock).length,
        withDiscount: products.filter((product) => (product.discount_amount_cents || 0) > 0).length,
    }), [products]);

    const requestHeaders = {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
    };

    async function loadData() {
        setLoading(true);
        const meResponse = await fetch('/api/me', { headers: { Accept: 'application/json' } });
        const me = await meResponse.json();
        const isSeller = me.role === 'seller';
        const [productsResponse, linksResponse, statsResponse, settingsResponse, notificationsResponse, tenantResponse] = await Promise.all([
            fetch('/api/products', { headers: { Accept: 'application/json' } }),
            fetch('/api/sales-links', { headers: { Accept: 'application/json' } }),
            fetch('/api/dashboard', { headers: { Accept: 'application/json' } }),
            isSeller ? Promise.resolve(null) : fetch('/api/payment-settings', { headers: { Accept: 'application/json' } }),
            isSeller ? Promise.resolve(null) : fetch('/api/notification-settings', { headers: { Accept: 'application/json' } }),
            isSeller ? Promise.resolve(null) : fetch('/api/tenant-settings', { headers: { Accept: 'application/json' } }),
        ]);

        const paymentSettings = settingsResponse ? await settingsResponse.json() : { sandbox: true, statement_descriptor: 'STORE TI', configured: false };
        const notificationData = notificationsResponse ? await notificationsResponse.json() : { settings: {}, contacts: [], logs: [] };
        const tenantData = tenantResponse ? await tenantResponse.json() : me.tenant || {};
        setCurrentUser(me);
        setProducts(await productsResponse.json());
        setLinks(await linksResponse.json());
        setStats(await statsResponse.json());
        setSettings(paymentSettings);
        setSettingsForm({
            access_token: '',
            public_key: paymentSettings.public_key || '',
            sandbox: paymentSettings.sandbox,
            statement_descriptor: paymentSettings.statement_descriptor || 'STORE TI',
        });
        setNotificationSettings(notificationData.settings || {});
        setNotificationLogs(notificationData.logs || []);
        setNotificationForm({
            enabled: Boolean(notificationData.settings?.enabled),
            provider_enabled: Boolean(notificationData.settings?.provider_enabled),
            base_url: notificationData.settings?.base_url || '',
            instance: notificationData.settings?.instance || '',
            api_key: '',
            dynamic_customer_enabled: Boolean(notificationData.settings?.dynamic_customer_enabled),
            notify_sale_created: Boolean(notificationData.settings?.notify_sale_created ?? true),
            notify_payment_approved: Boolean(notificationData.settings?.notify_payment_approved ?? true),
            sale_created_message: notificationData.settings?.sale_created_message || '',
            payment_approved_message: notificationData.settings?.payment_approved_message || '',
            contacts: notificationData.contacts?.length
                ? notificationData.contacts.map((contact) => ({
                    id: contact.id,
                    name: contact.name || '',
                    phone: contact.phone || '',
                    active: Boolean(contact.active),
                }))
                : [{ name: '', phone: '', active: true }],
        });
        setTenant(tenantData);
        setStoreThemes(tenantData.store_themes || []);
        setTenantForm({
            name: tenantData.name || 'Store TI',
            active: Boolean(tenantData.active ?? true),
            document: tenantData.document || '',
            support_phone: tenantData.support_phone || '',
            support_email: tenantData.support_email || '',
            store_slug: tenantData.store_slug || 'store-ti',
            store_theme: tenantData.store_theme || 'default',
            store_title: tenantData.store_title || tenantData.name || 'Store TI',
            store_subtitle: tenantData.store_subtitle || '',
            store_banner_label: tenantData.store_banner_label || '',
            store_banner_image_url: tenantData.store_banner_image_url || '',
            store_featured_image_url: tenantData.store_featured_image_url || '',
            store_featured_label: tenantData.store_featured_label || 'Ofertas do dia',
            store_featured_title: tenantData.store_featured_title || 'Ate 20% OFF',
            store_featured_subtitle: tenantData.store_featured_subtitle || 'Em itens selecionados',
            store_featured_cta: tenantData.store_featured_cta || 'Ver',
            store_secure_image_url: tenantData.store_secure_image_url || '',
            store_secure_label: tenantData.store_secure_label || 'Protecao',
            store_secure_title: tenantData.store_secure_title || 'Compra segura',
            store_secure_subtitle: tenantData.store_secure_subtitle || 'Pix e acompanhamento pelo painel',
            store_secure_cta: tenantData.store_secure_cta || 'Ver',
            store_shipping_regions: (tenantData.store_shipping_regions || defaultShippingRegions).map((region) => ({
                ...region,
                price: String((region.price_cents ?? 0) / 100),
            })),
            admin_primary_color: tenantData.admin_primary_color || '#111c22',
            admin_accent_color: tenantData.admin_accent_color || '#0f766e',
            checkout_primary_color: tenantData.checkout_primary_color || '#3b82f6',
            checkout_button_color: tenantData.checkout_button_color || '#43c97b',
            active_payment_provider: tenantData.active_payment_provider || 'mercado_pago',
            payment_providers: tenantData.payment_providers || {},
            payment_credentials: tenantData.payment_credentials || {},
        });
        if (me.role === 'superadmin') {
            const [usersResponse, companiesResponse] = await Promise.all([
                fetch('/api/users', { headers: { Accept: 'application/json' } }),
                fetch('/api/companies', { headers: { Accept: 'application/json' } }),
            ]);
            setUsers(usersResponse.ok ? await usersResponse.json() : []);
            setCompanies(companiesResponse.ok ? await companiesResponse.json() : []);
            if (tenantData.id) {
                const customersResponse = await fetch('/api/customers', { headers: { Accept: 'application/json' } });
                setCustomers(customersResponse.ok ? await customersResponse.json() : []);
            } else {
                setCustomers([]);
            }
        } else if (me.role === 'admin') {
            const usersResponse = await fetch('/api/users', { headers: { Accept: 'application/json' } });
            setUsers(usersResponse.ok ? await usersResponse.json() : []);
            const customersResponse = await fetch('/api/customers', { headers: { Accept: 'application/json' } });
            setCustomers(customersResponse.ok ? await customersResponse.json() : []);
            setCompanies([]);
        } else {
            setUsers([]);
            setCustomers([]);
            setCompanies([]);
        }
        setLoading(false);
    }

    useEffect(() => {
        if (!tenant) return;

        document.documentElement.style.setProperty('--admin-primary-color', tenant.admin_primary_color || '#111c22');
        document.documentElement.style.setProperty('--admin-accent-color', tenant.admin_accent_color || '#0f766e');
        document.documentElement.style.setProperty('--checkout-primary-color', tenant.checkout_primary_color || '#3b82f6');
        document.documentElement.style.setProperty('--checkout-button-color', tenant.checkout_button_color || '#43c97b');
    }, [tenant]);

    useEffect(() => {
        if (currentUser?.role === 'seller' && !['dashboard', 'sales'].includes(activeSection)) {
            setActiveSection('dashboard');
        }
    }, [currentUser, activeSection]);

    async function reloadAll() {
        await Promise.all([
            loadData(),
            loadReports(),
        ]);
    }

    async function loadReports(period = reportPeriod) {
        if (currentUser?.role === 'seller') {
            return;
        }

        const params = new URLSearchParams(period);
        const response = await fetch(`/api/reports?${params.toString()}`, { headers: { Accept: 'application/json' } });

        if (response.ok) {
            setReports(await response.json());
        }
    }

    useEffect(() => {
        if (!isLoginPage && !isCheckoutResultPage && !isPublicSalesPage && !isPublicProductPage && !isPublicStorePage && !isPublicStoreSearchPage && !isPublicCustomerLoginPage && !isPublicCustomerRegisterPage) {
            loadData();
            loadReports();
        }
    }, [isLoginPage, isCheckoutResultPage, isPublicSalesPage, isPublicProductPage, isPublicStorePage, isPublicStoreSearchPage, isPublicCustomerLoginPage, isPublicCustomerRegisterPage]);

    async function applyReportPeriod(event) {
        event.preventDefault();
        await loadReports(reportPeriod);
    }

    async function setQuickReportPeriod(days) {
        const to = new Date();
        const from = new Date();
        from.setDate(to.getDate() - (days - 1));
        const nextPeriod = { from: dateInputValue(from), to: dateInputValue(to) };
        setReportPeriod(nextPeriod);
        await loadReports(nextPeriod);
    }

    async function submitLogin(event) {
        event.preventDefault();
        setAuthenticating(true);
        setNotice('');

        const response = await fetch('/login', {
            method: 'POST',
            headers: requestHeaders,
            body: JSON.stringify(loginForm),
        });

        if (response.ok) {
            const next = new URLSearchParams(window.location.search).get('next');
            window.location.href = next && next.startsWith('/') ? next : '/';
            return;
        }

        setNotice('E-mail ou senha invalidos.');
        setAuthenticating(false);
    }

    async function logout() {
        await fetch('/logout', {
            method: 'POST',
            headers: requestHeaders,
        });
        window.location.href = '/login';
    }

    async function submitProduct(event) {
        event.preventDefault();
        setSavingProduct(true);
        setNotice('');

        const response = await fetch(editingProductId ? `/api/products/${editingProductId}` : '/api/products', {
            method: editingProductId ? 'PUT' : 'POST',
            headers: requestHeaders,
            body: JSON.stringify(productPayload(productForm)),
        });

        if (!response.ok) {
            setNotice('Revise os dados do produto.');
        } else {
            const product = await response.json();
            resetProductForm();
            setLinkForm((current) => ({ ...current, product_id: product.id, title: product.name }));
            await loadData();
            setNotice(editingProductId ? 'Produto atualizado.' : 'Produto cadastrado.');
        }

        setSavingProduct(false);
    }

    async function uploadStorefrontImage(file, applyUrl) {
        if (!file) return;

        const formData = new FormData();
        formData.append('image', file);

        const response = await fetch('/api/storefront-media', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        });

        const data = await response.json();

        if (!response.ok) {
            setNotice(data.message || 'Nao foi possivel enviar a imagem.');
            return;
        }

        applyUrl(data.url);
        setNotice('Imagem enviada.');
    }


    function resetProductForm() {
        setEditingProductId(null);
        setProductFormOpen(false);
        setProductForm({
            name: '',
            sku: '',
            type: 'internet_plan',
            description: '',
            image_url: '',
            gallery_urls_text: '',
            sizes_text: '',
            colors_text: '',
            variants_text: '',
            requires_shipping: false,
            shipping_weight_grams: '',
            price: '',
            discount_type: 'none',
            discount_value: '',
            track_stock: false,
            stock: '',
            billing_cycle: 'monthly',
            active: true,
        });
    }

    function openNewProductForm() {
        setEditingProductId(null);
        setProductForm({
            name: '',
            sku: '',
            type: 'internet_plan',
            description: '',
            image_url: '',
            gallery_urls_text: '',
            sizes_text: '',
            colors_text: '',
            variants_text: '',
            requires_shipping: false,
            shipping_weight_grams: '',
            price: '',
            discount_type: 'none',
            discount_value: '',
            track_stock: false,
            stock: '',
            billing_cycle: 'monthly',
            active: true,
        });
        setProductFormOpen(true);
    }

    function editProduct(product) {
        setEditingProductId(product.id);
        setProductFormOpen(true);
        setProductForm({
            name: product.name || '',
            sku: product.sku || '',
            type: product.type || 'internet_plan',
            description: product.description || '',
            image_url: product.image_url || '',
            gallery_urls_text: listToText(product.gallery_urls),
            sizes_text: listToText(product.options?.sizes),
            colors_text: listToText(product.options?.colors),
            variants_text: variantsToText(product.options?.variants),
            requires_shipping: Boolean(product.requires_shipping),
            shipping_weight_grams: product.shipping_weight_grams ? String(product.shipping_weight_grams) : '',
            price: product.price_cents ? String(product.price_cents / 100) : '',
            discount_type: product.discount_type || 'none',
            discount_value: product.discount_type === 'fixed'
                ? String((product.discount_value_cents || 0) / 100)
                : product.discount_type === 'percent'
                    ? String(product.discount_percent || 0)
                    : '',
            track_stock: Boolean(product.track_stock),
            stock: product.track_stock ? String(product.stock ?? 0) : '',
            billing_cycle: product.billing_cycle || 'monthly',
            active: Boolean(product.active),
        });
        setActiveSection('products');
    }

    async function deleteProduct(product) {
        if (!window.confirm(`Excluir "${product.name}"?`)) return;

        const response = await fetch(`/api/products/${product.id}`, {
            method: 'DELETE',
            headers: requestHeaders,
        });

        if (response.ok) {
            if (editingProductId === product.id) resetProductForm();
            await loadData();
            setNotice('Produto excluido.');
        } else {
            setNotice('Nao foi possivel excluir o produto.');
        }
    }

    async function submitLink(event) {
        event.preventDefault();
        setSavingLink(true);
        setNotice('');

        const payload = {
            ...linkForm,
            expires_at: linkForm.expires_at ? new Date(linkForm.expires_at).toISOString() : null,
        };

        const response = await fetch('/api/sales-links', {
            method: 'POST',
            headers: requestHeaders,
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            setNotice('Nao foi possivel gerar o link. Confira os campos e as credenciais.');
        } else {
            setLinkForm({
                product_id: payload.product_id,
                title: selectedProduct?.name || '',
                customer_email: '',
                quantity: 1,
                discount_type: 'none',
                discount_value: '',
                expires_at: '',
            });
            await loadData();
            setNotice('Link criado.');
        }

        setSavingLink(false);
    }

    async function submitSettings(event) {
        event.preventDefault();
        setSavingSettings(true);
        setNotice('');

        const response = await fetch('/api/payment-settings', {
            method: 'PUT',
            headers: requestHeaders,
            body: JSON.stringify(settingsForm),
        });

        if (!response.ok) {
            setNotice('Nao foi possivel salvar a configuracao do Mercado Pago.');
        } else {
            await loadData();
            setNotice('Configuracao de pagamento salva.');
        }

        setSavingSettings(false);
    }

    async function submitTenant(event) {
        event.preventDefault();
        setSavingTenant(true);
        setNotice('');

        const response = await fetch('/api/tenant-settings', {
            method: 'PUT',
            headers: requestHeaders,
            body: JSON.stringify(tenantForm),
        });

        if (!response.ok) {
            const data = await response.json();
            setNotice(data.message || 'Nao foi possivel salvar empresa, tema e provedor.');
        } else {
            await loadData();
            setNotice('Empresa, tema e provedor salvos.');
        }

        setSavingTenant(false);
    }

    function resetThemeForm() {
        setThemeForm({
            id: null,
            name: '',
            slug: '',
            primary_color: '#3b82f6',
            accent_color: '#43c97b',
            background_color: '#eef2f4',
            banner_label: '',
            banner_image_url: '',
            featured_image_url: '',
            featured_title: '',
            featured_subtitle: '',
            active: true,
        });
    }

    function editStoreTheme(theme) {
        setThemeForm({
            id: theme.id,
            name: theme.name || '',
            slug: theme.slug || '',
            primary_color: theme.primary_color || '#3b82f6',
            accent_color: theme.accent_color || '#43c97b',
            background_color: theme.background_color || '#eef2f4',
            banner_label: theme.banner_label || '',
            banner_image_url: theme.banner_image_url || '',
            featured_image_url: theme.featured_image_url || '',
            featured_title: theme.featured_title || '',
            featured_subtitle: theme.featured_subtitle || '',
            active: Boolean(theme.active),
        });
    }

    async function submitStoreTheme(event) {
        event.preventDefault();
        const response = await fetch(themeForm.id ? `/api/store-themes/${themeForm.id}` : '/api/store-themes', {
            method: themeForm.id ? 'PUT' : 'POST',
            headers: requestHeaders,
            body: JSON.stringify({
                ...themeForm,
                slug: themeForm.slug || slugify(themeForm.name),
            }),
        });

        if (!response.ok) {
            setNotice('Nao foi possivel salvar o tema.');
            return;
        }

        resetThemeForm();
        await loadData();
        setNotice('Tema da loja salvo.');
    }

    async function deleteStoreTheme(theme) {
        if (!window.confirm(`Excluir o tema "${theme.name}"?`)) return;

        const response = await fetch(`/api/store-themes/${theme.id}`, {
            method: 'DELETE',
            headers: requestHeaders,
        });

        if (response.ok) {
            await loadData();
            setNotice('Tema excluido.');
        } else {
            setNotice('Nao foi possivel excluir o tema.');
        }
    }

    async function submitUser(event) {
        event.preventDefault();
        setSavingUser(true);
        setNotice('');

        const response = await fetch(editingUserId ? `/api/users/${editingUserId}` : '/api/users', {
            method: editingUserId ? 'PUT' : 'POST',
            headers: requestHeaders,
            body: JSON.stringify(userForm),
        });

        if (!response.ok) {
            const data = await response.json();
            setNotice(data.message || 'Nao foi possivel salvar o usuario.');
        } else {
            resetUserForm();
            setUserFormOpen(false);
            await loadData();
            setNotice(editingUserId ? 'Usuario atualizado.' : 'Usuario criado.');
        }

        setSavingUser(false);
    }

    function resetUserForm() {
        setEditingUserId(null);
        setUserFormOpen(false);
        setUserForm({ name: '', email: '', password: '', tenant_setting_id: '', role: 'admin', active: true });
    }

    function openNewUserForm() {
        setEditingUserId(null);
        setUserForm({ name: '', email: '', password: '', tenant_setting_id: '', role: 'admin', active: true });
        setUserFormOpen(true);
    }

    function editUser(user) {
        setEditingUserId(user.id);
        setUserFormOpen(true);
        setUserForm({
            name: user.name || '',
            email: user.email || '',
            password: '',
            tenant_setting_id: user.tenant_setting_id || '',
            role: user.role || 'admin',
            active: Boolean(user.active),
        });
        setActiveSection('users');
    }

    async function deleteUser(user) {
        if (!window.confirm(`Excluir usuario "${user.name}"?`)) return;

        const response = await fetch(`/api/users/${user.id}`, {
            method: 'DELETE',
            headers: requestHeaders,
        });

        if (response.ok) {
            await loadData();
            setNotice('Usuario excluido.');
        } else {
            const data = await response.json();
            setNotice(data.message || 'Nao foi possivel excluir o usuario.');
        }
    }

    async function toggleCustomerActive(customer) {
        const response = await fetch(`/api/customers/${customer.id}`, {
            method: 'PUT',
            headers: requestHeaders,
            body: JSON.stringify({ ...customer, active: !customer.active }),
        });

        if (response.ok) {
            await loadData();
            setNotice(!customer.active ? 'Cliente ativado.' : 'Cliente desativado.');
        } else {
            const data = await response.json();
            setNotice(data.message || 'Nao foi possivel alterar o cliente.');
        }
    }

    async function deleteCustomer(customer) {
        if (!window.confirm(`Excluir cliente "${customer.name}"?`)) return;

        const response = await fetch(`/api/customers/${customer.id}`, {
            method: 'DELETE',
            headers: requestHeaders,
        });

        if (response.ok) {
            await loadData();
            setNotice('Cliente excluido.');
        } else {
            const data = await response.json();
            setNotice(data.message || 'Nao foi possivel excluir o cliente.');
        }
    }

    function updateProviderEnabled(provider, enabled) {
        setTenantForm((current) => ({
            ...current,
            payment_providers: {
                ...current.payment_providers,
                [provider]: {
                    ...(current.payment_providers?.[provider] || {}),
                    enabled,
                },
            },
        }));
    }

    function tenantToCompanyForm(company = tenantForm) {
        return {
            name: company.name || '',
            active: Boolean(company.active ?? true),
            document: company.document || '',
            support_phone: company.support_phone || '',
            support_email: company.support_email || '',
            store_slug: company.store_slug || '',
            store_theme: company.store_theme || 'default',
            store_title: company.store_title || company.name || '',
            store_subtitle: company.store_subtitle || '',
            store_banner_label: company.store_banner_label || '',
            store_banner_image_url: company.store_banner_image_url || '',
            store_featured_image_url: company.store_featured_image_url || '',
            store_featured_label: company.store_featured_label || 'Ofertas do dia',
            store_featured_title: company.store_featured_title || 'Ate 20% OFF',
            store_featured_subtitle: company.store_featured_subtitle || 'Em itens selecionados',
            store_featured_cta: company.store_featured_cta || 'Ver',
            store_secure_image_url: company.store_secure_image_url || '',
            store_secure_label: company.store_secure_label || 'Protecao',
            store_secure_title: company.store_secure_title || 'Compra segura',
            store_secure_subtitle: company.store_secure_subtitle || 'Pix e acompanhamento pelo painel',
            store_secure_cta: company.store_secure_cta || 'Ver',
            store_shipping_regions: (company.store_shipping_regions || tenantForm.store_shipping_regions || defaultShippingRegions).map((region) => ({
                ...region,
                price: String((region.price_cents ?? 0) / 100),
            })),
            admin_primary_color: company.admin_primary_color || '#111c22',
            admin_accent_color: company.admin_accent_color || '#0f766e',
            checkout_primary_color: company.checkout_primary_color || '#3b82f6',
            checkout_button_color: company.checkout_button_color || '#43c97b',
            active_payment_provider: company.active_payment_provider || 'mercado_pago',
            payment_providers: company.payment_providers || tenantForm.payment_providers || {},
            payment_credentials: company.payment_credentials || {},
        };
    }

    function resetCompanyForm() {
        setEditingCompanyId(null);
        setCompanyFormOpen(false);
        setCompanyForm(tenantToCompanyForm({
            name: '',
            payment_providers: tenantForm.payment_providers,
        }));
    }

    function openNewCompanyForm() {
        setEditingCompanyId(null);
        setCompanyForm(tenantToCompanyForm({
            name: '',
            payment_providers: tenantForm.payment_providers,
        }));
        setCompanyFormOpen(true);
    }

    function editCompany(company) {
        setEditingCompanyId(company.id);
        setCompanyFormOpen(true);
        setCompanyForm(tenantToCompanyForm(company));
        setActiveSection('companies');
    }

    function updateCompanyProviderEnabled(provider, enabled) {
        setCompanyForm((current) => ({
            ...current,
            payment_providers: {
                ...current.payment_providers,
                [provider]: {
                    ...(current.payment_providers?.[provider] || {}),
                    enabled,
                },
            },
        }));
    }

    function updateCompanyCredential(provider, field, value) {
        setCompanyForm((current) => ({
            ...current,
            payment_credentials: {
                ...(current.payment_credentials || {}),
                [provider]: {
                    ...(current.payment_credentials?.[provider] || {}),
                    [field]: value,
                },
            },
        }));
    }

    function updateTenantCredential(provider, field, value) {
        setTenantForm((current) => ({
            ...current,
            payment_credentials: {
                ...(current.payment_credentials || {}),
                [provider]: {
                    ...(current.payment_credentials?.[provider] || {}),
                    [field]: value,
                },
            },
        }));
    }

    async function toggleCompanyActive(company) {
        const response = await fetch(`/api/companies/${company.id}/status`, {
            method: 'PATCH',
            headers: requestHeaders,
            body: JSON.stringify({ active: !company.active }),
        });

        if (response.ok) {
            await loadData();
            setNotice(!company.active ? 'Empresa ativada.' : 'Empresa desativada.');
        } else {
            const data = await response.json();
            setNotice(data.message || 'Nao foi possivel alterar o status da empresa.');
        }
    }

    function productPayload(form) {
        return {
            ...form,
            gallery_urls: parseList(form.gallery_urls_text),
            options: {
                sizes: parseList(form.sizes_text),
                colors: parseList(form.colors_text),
                variants: parseVariantsText(form.variants_text),
            },
        };
    }

    function updateTenantShippingRegion(index, patch) {
        setTenantForm((current) => ({
            ...current,
            store_shipping_regions: current.store_shipping_regions.map((region, regionIndex) => (
                regionIndex === index ? { ...region, ...patch } : region
            )),
        }));
    }

    function updateCompanyShippingRegion(index, patch) {
        setCompanyForm((current) => ({
            ...current,
            store_shipping_regions: current.store_shipping_regions.map((region, regionIndex) => (
                regionIndex === index ? { ...region, ...patch } : region
            )),
        }));
    }

    function addTenantShippingRegion() {
        setTenantForm((current) => ({
            ...current,
            store_shipping_regions: [...current.store_shipping_regions, { region: '', cep_prefix: '', price: '0', eta: '' }],
        }));
    }

    function addCompanyShippingRegion() {
        setCompanyForm((current) => ({
            ...current,
            store_shipping_regions: [...current.store_shipping_regions, { region: '', cep_prefix: '', price: '0', eta: '' }],
        }));
    }

    async function submitCompany(event) {
        event.preventDefault();
        setSavingCompany(true);
        setNotice('');

        const response = await fetch(editingCompanyId ? `/api/companies/${editingCompanyId}` : '/api/companies', {
            method: editingCompanyId ? 'PUT' : 'POST',
            headers: requestHeaders,
            body: JSON.stringify(companyForm),
        });

        if (!response.ok) {
            const data = await response.json();
            setNotice(data.message || 'Nao foi possivel salvar a empresa.');
        } else {
            resetCompanyForm();
            await loadData();
            setNotice(editingCompanyId ? 'Empresa atualizada.' : 'Empresa criada.');
        }

        setSavingCompany(false);
    }

    async function operateCompany(company) {
        const response = await fetch(`/api/companies/${company.id}/activate`, {
            method: 'POST',
            headers: requestHeaders,
        });

        if (response.ok) {
            await loadData();
            setNotice(`Voce agora esta visualizando "${company.name}".`);
        } else {
            setNotice('Nao foi possivel visualizar esta empresa.');
        }
    }

    async function viewCompany(company) {
        await operateCompany(company);
        setActiveSection('dashboard');
    }

    async function stopViewingCompany() {
        const response = await fetch('/api/companies/deactivate', {
            method: 'POST',
            headers: requestHeaders,
        });

        if (response.ok) {
            setActiveSection('dashboard');
            await loadData();
            setNotice('Voce voltou para a visao geral da plataforma.');
        } else {
            setNotice('Nao foi possivel parar de visualizar a empresa.');
        }
    }

    async function deleteCompany(company) {
        if (!window.confirm(`Excluir empresa "${company.name}"?`)) return;

        const response = await fetch(`/api/companies/${company.id}`, {
            method: 'DELETE',
            headers: requestHeaders,
        });

        if (response.ok) {
            await loadData();
            setNotice('Empresa excluida.');
        } else {
            const data = await response.json();
            setNotice(data.message || 'Nao foi possivel excluir a empresa.');
        }
    }

    async function submitNotifications(event) {
        event.preventDefault();
        setSavingNotifications(true);
        setNotice('');

        const response = await fetch('/api/notification-settings', {
            method: 'PUT',
            headers: requestHeaders,
            body: JSON.stringify({
                ...notificationForm,
                contacts: notificationForm.contacts.filter((contact) => contact.name.trim() && contact.phone.trim()),
            }),
        });

        if (!response.ok) {
            setNotice(notificationSettings.can_manage_provider
                ? 'Nao foi possivel salvar. Confira URL, instancia, API key e contatos.'
                : 'Nao foi possivel salvar. Confira regras, mensagens e contatos.');
        } else {
            await loadData();
            setNotice('Configuracao de notificacoes salva.');
        }

        setSavingNotifications(false);
    }

    async function testNotification(contact) {
        if (!contact?.phone) {
            setNotice('Informe um telefone para testar.');
            return;
        }

        setTestingNotifications(true);
        setNotice('');

        const response = await fetch('/api/notification-settings/test', {
            method: 'POST',
            headers: requestHeaders,
            body: JSON.stringify({ phone: contact.phone, name: contact.name }),
        });

        await loadData();
        setNotice(response.ok ? 'Teste de notificacao enviado.' : 'Nao foi possivel enviar o teste.');
        setTestingNotifications(false);
    }

    function updateNotificationContact(index, field, value) {
        setNotificationForm((current) => ({
            ...current,
            contacts: current.contacts.map((contact, contactIndex) => (
                contactIndex === index ? { ...contact, [field]: value } : contact
            )),
        }));
    }

    function addNotificationContact() {
        setNotificationForm((current) => ({
            ...current,
            contacts: [...current.contacts, { name: '', phone: '', active: true }],
        }));
    }

    function removeNotificationContact(index) {
        setNotificationForm((current) => ({
            ...current,
            contacts: current.contacts.filter((_, contactIndex) => contactIndex !== index),
        }));
    }

    async function copyUrl(url) {
        if (!url) return;
        await navigator.clipboard.writeText(url);
        setNotice('Link copiado.');
    }

    async function refreshLink(id) {
        setNotice('');

        const response = await fetch(`/api/sales-links/${id}/refresh`, {
            method: 'POST',
            headers: requestHeaders,
        });
        const data = await response.json();

        await loadData();
        setNotice(data.message || (response.ok ? 'Venda sincronizada.' : 'Nao foi possivel sincronizar a venda.'));
    }

    async function updateSaleStatus(id, status) {
        const response = await fetch(`/api/sales-links/${id}`, {
            method: 'PATCH',
            headers: requestHeaders,
            body: JSON.stringify({ status }),
        });

        if (response.ok) {
            await loadData();
            setNotice('Status da venda atualizado.');
        } else {
            setNotice('Nao foi possivel atualizar a venda.');
        }
    }

    async function updateSaleDelivery(id, payload) {
        const response = await fetch(`/api/sales-links/${id}`, {
            method: 'PATCH',
            headers: requestHeaders,
            body: JSON.stringify(payload),
        });

        if (response.ok) {
            await loadData();
            setNotice('Entrega atualizada.');
        } else {
            setNotice('Nao foi possivel atualizar a entrega.');
        }
    }

    async function deleteSale(id) {
        if (!window.confirm('Excluir esta venda?')) return;

        const response = await fetch(`/api/sales-links/${id}`, {
            method: 'DELETE',
            headers: requestHeaders,
        });

        if (response.ok) {
            await loadData();
            setNotice('Venda excluida.');
        } else {
            setNotice('Nao foi possivel excluir a venda.');
        }
    }

    if (isLoginPage) {
        return (
            <main className="login-shell">
                <form className="login-panel" onSubmit={submitLogin}>
                    <div>
                        <p className="eyebrow">Store TI</p>
                        <h1>Acesso administrativo</h1>
                    </div>
                    {notice && <div className="notice">{notice}</div>}
                    <Field label="E-mail">
                        <input
                            type="email"
                            value={loginForm.email}
                            onChange={(event) => setLoginForm({ ...loginForm, email: event.target.value })}
                            autoComplete="email"
                            required
                        />
                    </Field>
                    <Field label="Senha">
                        <input
                            type="password"
                            value={loginForm.password}
                            onChange={(event) => setLoginForm({ ...loginForm, password: event.target.value })}
                            autoComplete="current-password"
                            required
                        />
                    </Field>
                    <label className="toggle">
                        <input
                            type="checkbox"
                            checked={loginForm.remember}
                            onChange={(event) => setLoginForm({ ...loginForm, remember: event.target.checked })}
                        />
                        <span>Manter conectado</span>
                    </label>
                    <button className="primary-button" disabled={authenticating}>
                        <CheckCircle2 size={18} />
                        {authenticating ? 'Entrando...' : 'Entrar'}
                    </button>
                </form>
            </main>
        );
    }

    if (isCheckoutResultPage) {
        return <CheckoutResult />;
    }

    if (isPublicStorePage || isPublicStoreSearchPage) {
        return <PublicStorePage />;
    }

    if (isPublicCustomerLoginPage || isPublicCustomerRegisterPage) {
        return <PublicCustomerAccountPage mode={isPublicCustomerRegisterPage ? 'register' : 'login'} />;
    }

    if (isPublicSalesPage || isPublicProductPage) {
        return <PublicSalesPage mode={isPublicProductPage ? 'product' : 'sale'} />;
    }

    const hasCompanyContext = currentUser?.role !== 'superadmin' || Boolean(tenant?.id);
    const isSellerUser = currentUser?.role === 'seller';
    const canManageCompany = hasCompanyContext && !isSellerUser;

    return (
        <main className="admin-shell">
            <aside className="sidebar">
                <div className="brand-block">
                    <span>{hasCompanyContext ? (tenant?.name || 'Store TI') : 'Plataforma'}</span>
                    <strong>{hasCompanyContext ? 'Vendas' : 'Superadmin'}</strong>
                </div>
                <nav className="side-nav">
                    <button className={activeSection === 'dashboard' ? 'active' : ''} onClick={() => setActiveSection('dashboard')}>
                        <BarChart3 size={18} /> Dashboard
                    </button>
                    {hasCompanyContext && (
                        <>
                            {canManageCompany && (
                                <button className={activeSection === 'reports' ? 'active' : ''} onClick={() => setActiveSection('reports')}>
                                    <FileText size={18} /> Relatorios
                                </button>
                            )}
                            <button className={activeSection === 'sales' ? 'active' : ''} onClick={() => setActiveSection('sales')}>
                                <ShoppingCart size={18} /> Vendas
                            </button>
                            {canManageCompany && (
                                <>
                                    <button className={activeSection === 'products' ? 'active' : ''} onClick={() => setActiveSection('products')}>
                                        <Box size={18} /> Produtos
                                    </button>
                                    <button className={activeSection === 'customers' ? 'active' : ''} onClick={() => setActiveSection('customers')}>
                                        <Users size={18} /> Clientes
                                    </button>
                                    <button className={activeSection === 'settings' ? 'active' : ''} onClick={() => setActiveSection('settings')}>
                                        <Settings size={18} /> Configuracoes
                                    </button>
                                    <button className={activeSection === 'notifications' ? 'active' : ''} onClick={() => setActiveSection('notifications')}>
                                        <Bell size={18} /> Notificacoes
                                    </button>
                                </>
                            )}
                        </>
                    )}
                    {(currentUser?.role === 'superadmin' || currentUser?.role === 'admin') && (
                        <>
                            {currentUser?.role === 'superadmin' && (
                                <>
                                    <button className={activeSection === 'settings' ? 'active' : ''} onClick={() => setActiveSection('settings')}>
                                        <Settings size={18} /> Configuracoes
                                    </button>
                                    <button className={activeSection === 'companies' ? 'active' : ''} onClick={() => setActiveSection('companies')}>
                                        <Box size={18} /> Empresas
                                    </button>
                                </>
                            )}
                            <button className={activeSection === 'users' ? 'active' : ''} onClick={() => setActiveSection('users')}>
                                <Users size={18} /> Usuarios
                            </button>
                        </>
                    )}
                </nav>
                <button className="sidebar-logout" onClick={logout}>Sair</button>
            </aside>

            <section className="admin-content">
                {loading && (
                    <div className="platform-loading-bar">
                        <Loader label="Atualizando dados da plataforma" />
                    </div>
                )}
                <header className="topbar">
                    <div>
                        <p className="eyebrow">Painel administrativo</p>
                        <h1>{{
                            dashboard: 'Visao geral',
                            reports: 'Relatorios',
                            sales: 'Gerenciar vendas',
                            products: 'Produtos e links',
                            customers: 'Clientes da loja',
                            settings: 'Configuracoes',
                            notifications: 'Notificacoes',
                            companies: 'Empresas / tenants',
                            users: 'Gerenciar usuarios',
                        }[activeSection]}</h1>
                    </div>
                    <div className="topbar-actions">
                        {currentUser?.role === 'superadmin' && tenant?.id && (
                            <button className="secondary-button stop-context-button" type="button" onClick={stopViewingCompany}>
                                <X size={17} />
                                Parar de visualizar empresa
                            </button>
                        )}
                        <button className="icon-button" onClick={reloadAll} title="Atualizar dados">
                            <RefreshCcw size={18} />
                        </button>
                    </div>
                </header>

                {notice && <div className="notice">{notice}</div>}

                {activeSection === 'dashboard' && (
                    stats.mode === 'superadmin' ? (
                        <SuperAdminDashboard stats={stats} operateCompany={operateCompany} editCompany={editCompany} />
                    ) : (
                        <>
                            <section className="metrics" aria-busy={loading}>
                                <Metric icon={Box} label="Produtos" value={stats.products ?? 0} />
                                <Metric icon={LinkIcon} label="Vendas geradas" value={stats.links ?? 0} />
                                <Metric icon={Activity} label="Pendentes" value={stats.pending_links ?? 0} />
                                <Metric icon={CreditCard} label="Receita" value={money(stats.revenue_cents ?? 0)} />
                            </section>
                            <section className="system-health">
                                <HealthCard
                                    title="Mercado Pago"
                                    description={settings.configured ? 'Pix configurado para gerar pagamentos.' : 'Configure o token para liberar cobranças.'}
                                    tone={settings.configured ? 'ready' : 'draft'}
                                    value={settings.configured ? 'Ativo' : 'Pendente'}
                                />
                                <HealthCard
                                    title="Evolution"
                                    description={notificationSettings.configured ? 'Notificações prontas para vendas e pagamentos.' : 'Configure instância e API key para WhatsApp.'}
                                    tone={notificationSettings.configured ? 'ready' : 'draft'}
                                    value={notificationSettings.configured ? 'Ativo' : 'Pendente'}
                                />
                                <HealthCard
                                    title="Ticket médio"
                                    description="Média das vendas pagas confirmadas."
                                    tone="neutral"
                                    value={money(stats.average_ticket_cents ?? 0)}
                                />
                            </section>
                            <section className="status-board">
                                <StatusTile label="Nao configurados" value={salesByStatus.draft} tone="draft" />
                                <StatusTile label="Prontos para vender" value={salesByStatus.ready} tone="ready" />
                                <StatusTile label="Em andamento" value={salesByStatus.pending} tone="pending" />
                                <StatusTile label="Pagos" value={salesByStatus.paid} tone="paid" />
                                <StatusTile label="Cancelados" value={salesByStatus.cancelled} tone="cancelled" />
                            </section>
                            <SalesList title="Ultimas vendas" links={links.slice(0, 5)} copyUrl={copyUrl} refreshLink={refreshLink} updateSaleStatus={updateSaleStatus} updateSaleDelivery={updateSaleDelivery} deleteSale={deleteSale} />
                        </>
                    )
                )}

                {activeSection === 'reports' && canManageCompany && (
                    <ReportsPanel
                        reports={reports}
                        reportPeriod={reportPeriod}
                        setReportPeriod={setReportPeriod}
                        applyReportPeriod={applyReportPeriod}
                        setQuickReportPeriod={setQuickReportPeriod}
                    />
                )}

                {activeSection === 'sales' && (
                    <>
                        <section className="management-toolbar">
                            <div>
                                <h2>Controle comercial</h2>
                                <p>Acompanhe cliente, pagamento, status e sincronização com Mercado Pago.</p>
                            </div>
                            <div className="toolbar-controls">
                                <label className="search-field">
                                    <Search size={17} />
                                    <input value={saleSearch} onChange={(event) => setSaleSearch(event.target.value)} placeholder="Buscar venda, cliente, CPF..." />
                                </label>
                                <select value={saleStatusFilter} onChange={(event) => setSaleStatusFilter(event.target.value)}>
                                    <option value="all">Todos os status</option>
                                    <option value="ready">Prontos</option>
                                    <option value="pending">Pendentes</option>
                                    <option value="paid">Pagos</option>
                                    <option value="cancelled">Cancelados</option>
                                    <option value="draft">Nao configurados</option>
                                </select>
                            </div>
                        </section>
                        <SalesList title="Vendas" links={filteredLinks} copyUrl={copyUrl} refreshLink={refreshLink} updateSaleStatus={updateSaleStatus} updateSaleDelivery={updateSaleDelivery} deleteSale={deleteSale} />
                    </>
                )}

                {activeSection === 'products' && canManageCompany && (
                    <>
                    <section className="product-overview">
                        <StatusTile label="Ativos" value={productOverview.active} tone="ready" />
                        <StatusTile label="Planos de internet" value={productOverview.internetPlans} tone="paid" />
                        <StatusTile label="Sem estoque" value={productOverview.withoutStockControl} tone="pending" />
                        <StatusTile label="Com desconto" value={productOverview.withDiscount} tone="draft" />
                    </section>
                    <section className="listing-page products-page">
                        {productFormOpen && (
                        <div className="storefront-modal-backdrop admin-modal-backdrop" onClick={resetProductForm}>
                        <form className="panel structured-panel admin-form-modal product-form-modal" onSubmit={submitProduct} onClick={(event) => event.stopPropagation()}>
                            <div className="panel-title">
                                <PackagePlus size={20} />
                                <h2>{editingProductId ? 'Editar produto' : 'Cadastrar produto'}</h2>
                                {editingProductId && (
                                    <button className="icon-button" type="button" title="Cancelar edicao" onClick={resetProductForm}>
                                        <X size={17} />
                                    </button>
                                )}
                            </div>
                            <div className="form-section-title">
                                <strong>Dados da oferta</strong>
                                <span>Informações que aparecem no checkout público.</span>
                            </div>
                            <Field label="Nome">
                                <input value={productForm.name} onChange={(event) => setProductForm({ ...productForm, name: event.target.value })} required />
                            </Field>
                            <div className="form-grid">
                                <Field label="Tipo">
                                    <select
                                        value={productForm.type}
                                        onChange={(event) => {
                                            const type = event.target.value;
                                            const isPhysical = type === 'physical';
                                            setProductForm({
                                                ...productForm,
                                                type,
                                                track_stock: isPhysical,
                                                stock: isPhysical ? (productForm.stock || 1) : '',
                                                billing_cycle: isPhysical ? 'one_time' : 'monthly',
                                            });
                                        }}
                                    >
                                        <option value="internet_plan">Plano de internet</option>
                                        <option value="service">Servico</option>
                                        <option value="subscription">Assinatura</option>
                                        <option value="physical">Produto fisico</option>
                                        <option value="other">Outro</option>
                                    </select>
                                </Field>
                                <Field label="Cobranca">
                                    <select value={productForm.billing_cycle} onChange={(event) => setProductForm({ ...productForm, billing_cycle: event.target.value })}>
                                        <option value="monthly">Mensal</option>
                                        <option value="quarterly">Trimestral</option>
                                        <option value="semiannual">Semestral</option>
                                        <option value="annual">Anual</option>
                                        <option value="one_time">Pagamento unico</option>
                                        <option value="none">Sem recorrencia</option>
                                    </select>
                                </Field>
                                <Field label="SKU">
                                    <input value={productForm.sku} onChange={(event) => setProductForm({ ...productForm, sku: event.target.value })} />
                                </Field>
                                {productForm.track_stock && (
                                    <Field label="Estoque">
                                        <input type="number" min="0" value={productForm.stock} onChange={(event) => setProductForm({ ...productForm, stock: event.target.value })} required />
                                    </Field>
                                )}
                            </div>
                            <div className="form-section-title">
                                <strong>Preço e desconto</strong>
                                <span>O link público usa o valor final automaticamente.</span>
                            </div>
                            <Field label="Preco">
                                <input type="number" min="0.01" step="0.01" value={productForm.price} onChange={(event) => setProductForm({ ...productForm, price: event.target.value })} required />
                            </Field>
                            <div className="form-grid">
                                <Field label="Tipo de desconto">
                                    <select value={productForm.discount_type} onChange={(event) => setProductForm({ ...productForm, discount_type: event.target.value, discount_value: '' })}>
                                        <option value="none">Sem desconto</option>
                                        <option value="fixed">Valor fixo</option>
                                        <option value="percent">Percentual</option>
                                    </select>
                                </Field>
                                <Field label="Desconto">
                                    <input type="number" min="0" step="0.01" disabled={productForm.discount_type === 'none'} value={productForm.discount_value} onChange={(event) => setProductForm({ ...productForm, discount_value: event.target.value })} />
                                </Field>
                            </div>
                            <Field label="Descricao">
                                <textarea value={productForm.description} onChange={(event) => setProductForm({ ...productForm, description: event.target.value })} rows="4" />
                            </Field>
                            <div className="form-section-title">
                                <strong>Midia e variacoes da loja</strong>
                                <span>Use foto quando tiver produto fisico. Se nao tiver imagem, a loja usa cores e tema.</span>
                            </div>
                            <div className="form-grid">
                                <Field label="Foto principal (URL)">
                                    <input value={productForm.image_url} onChange={(event) => setProductForm({ ...productForm, image_url: event.target.value })} placeholder="https://..." />
                                    <input type="file" accept="image/*" onChange={(event) => uploadStorefrontImage(event.target.files?.[0], (url) => setProductForm((current) => ({ ...current, image_url: url })))} />
                                </Field>
                                <Field label="Galeria (URLs separadas por virgula)">
                                    <input value={productForm.gallery_urls_text} onChange={(event) => setProductForm({ ...productForm, gallery_urls_text: event.target.value })} placeholder="https://foto1, https://foto2" />
                                </Field>
                                <Field label="Tamanhos">
                                    <input value={productForm.sizes_text} onChange={(event) => setProductForm({ ...productForm, sizes_text: event.target.value })} placeholder="P, M, G, 42" />
                                </Field>
                                <Field label="Cores">
                                    <input value={productForm.colors_text} onChange={(event) => setProductForm({ ...productForm, colors_text: event.target.value })} placeholder="Preto, Azul, Vermelho" />
                                </Field>
                                <Field label="Variacoes com preco/imagem">
                                    <textarea
                                        value={productForm.variants_text}
                                        onChange={(event) => setProductForm({ ...productForm, variants_text: event.target.value })}
                                        rows="4"
                                        placeholder={'256GB | Preto | 6749.10 | https://foto-preto.jpg\n512GB | Azul | 7499.90 | https://foto-azul.jpg'}
                                    />
                                </Field>
                            </div>
                            <div className="form-grid">
                                <label className="toggle">
                                    <input type="checkbox" checked={productForm.requires_shipping} onChange={(event) => setProductForm({ ...productForm, requires_shipping: event.target.checked })} />
                                    <span>Produto tem entrega</span>
                                </label>
                                <Field label="Peso aproximado (gramas)">
                                    <input type="number" min="0" value={productForm.shipping_weight_grams} onChange={(event) => setProductForm({ ...productForm, shipping_weight_grams: event.target.value })} disabled={!productForm.requires_shipping} />
                                </Field>
                            </div>
                            <label className="toggle">
                                <input type="checkbox" checked={productForm.track_stock} onChange={(event) => setProductForm({ ...productForm, track_stock: event.target.checked, stock: event.target.checked ? (productForm.stock || 1) : '' })} />
                                <span>Controlar estoque</span>
                            </label>
                            <label className="toggle">
                                <input type="checkbox" checked={productForm.active} onChange={(event) => setProductForm({ ...productForm, active: event.target.checked })} />
                                <span>Ativo para venda</span>
                            </label>
                            <button className="primary-button" disabled={savingProduct}>
                                <CheckCircle2 size={18} />
                                {savingProduct ? 'Salvando...' : editingProductId ? 'Atualizar produto' : 'Salvar produto'}
                            </button>
                        </form>
                        </div>
                        )}

                        <section className="panel product-list-panel">
                            <div className="users-table-toolbar">
                                <div className="panel-title-main">
                                    <Box size={20} />
                                    <div>
                                        <h2>Produtos cadastrados</h2>
                                        <p>{products.length} produto{products.length === 1 ? '' : 's'} no catalogo.</p>
                                    </div>
                                </div>
                                <span className="user-total-badge">{productOverview.active} ativos</span>
                                <button className="primary-button user-create-button" type="button" onClick={openNewProductForm}>
                                    <Plus size={18} />
                                    Novo produto
                                </button>
                            </div>
                            <div className="users-table-wrap">
                                <table className="users-table admin-data-table">
                                    <thead>
                                        <tr>
                                            <th>Produto</th>
                                            <th>Tipo</th>
                                            <th>Cobranca</th>
                                            <th>Estoque</th>
                                            <th>Valor</th>
                                            <th>Acoes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {products.map((product) => (
                                            <tr key={product.id}>
                                                <td>
                                                    <strong>{product.name}</strong>
                                                    <p>{product.sku || product.public_url}</p>
                                                </td>
                                                <td><span className="role-pill">{productTypeLabel[product.type] || 'Oferta'}</span></td>
                                                <td>{billingCycleLabel[product.billing_cycle] || 'Nao definida'}</td>
                                                <td>{product.track_stock ? product.stock : 'Sem controle'}</td>
                                                <td><strong>{money(product.final_amount_cents ?? product.price_cents)}</strong></td>
                                                <td>
                                                    <div className="row-actions user-actions">
                                                        <button className="icon-button" type="button" title="Copiar link do produto" onClick={() => copyUrl(product.public_url)}>
                                                            <Copy size={17} />
                                                        </button>
                                                        <button className="icon-button" type="button" title="Editar produto" onClick={() => editProduct(product)}>
                                                            <Pencil size={17} />
                                                        </button>
                                                        <a className="icon-link" href={product.public_url} target="_blank" rel="noreferrer" title="Abrir checkout">
                                                            <ExternalLink size={17} />
                                                        </a>
                                                        <button className="icon-button danger-action" type="button" title="Excluir produto" onClick={() => deleteProduct(product)}>
                                                            <Trash2 size={17} />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                {!products.length && <div className="empty-state">Nenhum produto cadastrado.</div>}
                            </div>
                        </section>
                    </section>
                    </>
                )}

                {activeSection === 'customers' && canManageCompany && (
                    <section className="listing-page customers-page">
                        <section className="panel customer-list-panel">
                            <div className="users-table-toolbar">
                                <div className="panel-title-main">
                                    <Users size={20} />
                                    <div>
                                        <h2>Clientes da loja</h2>
                                        <p>{customers.length} cliente{customers.length === 1 ? '' : 's'} cadastrado{customers.length === 1 ? '' : 's'}.</p>
                                    </div>
                                </div>
                                <span className="user-total-badge">{customers.filter((customer) => customer.active).length} ativos</span>
                            </div>
                            <div className="users-table-wrap">
                                <table className="users-table admin-data-table">
                                    <thead>
                                        <tr>
                                            <th>Cliente</th>
                                            <th>Contato</th>
                                            <th>Enderecos</th>
                                            <th>Status</th>
                                            <th>Acoes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {customers.map((customer) => (
                                            <tr key={customer.id}>
                                                <td>
                                                    <strong>{customer.name}</strong>
                                                    <p>{customer.email}</p>
                                                </td>
                                                <td>
                                                    {customer.phone || 'Sem telefone'}
                                                    <p>{formatCpf(customer.cpf)}</p>
                                                </td>
                                                <td>
                                                    <strong>{customer.addresses?.length || 0}</strong>
                                                    <p>{customer.addresses?.[0] ? `${customer.addresses[0].city}/${customer.addresses[0].state}` : 'Nenhum endereco'}</p>
                                                </td>
                                                <td><span className={`status ${customer.active ? 'ready' : 'cancelled'}`}>{customer.active ? 'Ativo' : 'Inativo'}</span></td>
                                                <td>
                                                    <div className="row-actions user-actions">
                                                        <button className="secondary-button company-view-button" type="button" onClick={() => toggleCustomerActive(customer)}>
                                                            {customer.active ? 'Desativar' : 'Ativar'}
                                                        </button>
                                                        <button className="icon-button danger-action" type="button" title="Excluir cliente" onClick={() => deleteCustomer(customer)}>
                                                            <Trash2 size={17} />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                {!customers.length && <div className="empty-state">Nenhum cliente cadastrado nesta loja.</div>}
                            </div>
                        </section>
                    </section>
                )}

                {activeSection === 'settings' && currentUser?.role === 'superadmin' && !hasCompanyContext && (
                    <section className="listing-page admin-config-page">
                        <section className="panel config-list-panel">
                            <div className="users-table-toolbar">
                                <div className="panel-title-main">
                                    <Settings size={20} />
                                    <div>
                                        <h2>Configuracoes da plataforma</h2>
                                        <p>Configure recursos globais usados por todas as empresas.</p>
                                    </div>
                                </div>
                                <span className={`user-total-badge ${notificationSettings.provider_configured ? '' : 'neutral'}`}>{notificationSettings.provider_configured ? 'Evolution ativo' : 'Evolution pendente'}</span>
                            </div>
                            <form className="platform-settings-grid" onSubmit={submitNotifications}>
                                <section className="panel settings-panel">
                                    <div className="panel-title">
                                        <Bell size={20} />
                                        <h2>Servidor Evolution global</h2>
                                        <span className={`status ${notificationSettings.provider_configured ? 'ready' : 'draft'}`}>{notificationSettings.provider_configured ? 'Configurado' : 'Pendente'}</span>
                                    </div>
                                    <div className="settings-grid notification-provider-grid">
                                        <Field label="URL do Evolution">
                                            <input value={notificationForm.base_url} onChange={(event) => setNotificationForm({ ...notificationForm, base_url: event.target.value })} placeholder="https://evolution.seudominio.com" />
                                        </Field>
                                        <Field label="Instancia">
                                            <input value={notificationForm.instance} onChange={(event) => setNotificationForm({ ...notificationForm, instance: event.target.value })} placeholder="store-ti" />
                                        </Field>
                                        <Field label="API key">
                                            <input type="password" value={notificationForm.api_key} onChange={(event) => setNotificationForm({ ...notificationForm, api_key: event.target.value })} placeholder={notificationSettings.provider_configured ? 'Chave salva. Preencha apenas para trocar.' : 'Sua apikey'} autoComplete="off" />
                                        </Field>
                                    </div>
                                    <label className="toggle notification-provider-toggle">
                                        <input type="checkbox" checked={notificationForm.provider_enabled} onChange={(event) => setNotificationForm({ ...notificationForm, provider_enabled: event.target.checked })} />
                                        <span>Instancia global ativa para todas as empresas</span>
                                    </label>
                                    <button className="primary-button notifications-save" disabled={savingNotifications}>
                                        <CheckCircle2 size={18} />
                                        {savingNotifications ? 'Salvando...' : 'Salvar configuracao global'}
                                    </button>
                                </section>
                            </form>
                        </section>
                    </section>
                )}

                {activeSection === 'settings' && canManageCompany && (
                    <section className="listing-page admin-config-page">
                        <section className="panel config-list-panel">
                            <div className="users-table-toolbar">
                                <div className="panel-title-main">
                                    <Settings size={20} />
                                    <div>
                                        <h2>Configuracoes da empresa</h2>
                                        <p>Gerencie dados da empresa, loja publica, frete e pagamentos.</p>
                                    </div>
                                </div>
                                <span className={`user-total-badge ${settings.configured ? '' : 'neutral'}`}>{settings.configured ? 'MP ativo' : 'MP pendente'}</span>
                                <button className="primary-button user-create-button" type="button" onClick={() => setSettingsFormOpen(true)}>
                                    <Pencil size={18} />
                                    Editar
                                </button>
                            </div>
                            <div className="users-table-wrap">
                                <table className="users-table admin-data-table config-modules-table">
                                    <thead>
                                        <tr>
                                            <th>Modulo</th>
                                            <th>Resumo</th>
                                            <th>Status</th>
                                            <th>Acoes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <strong>Empresa</strong>
                                                <p>Dados institucionais e suporte</p>
                                            </td>
                                            <td>
                                                {tenantForm.name || 'Empresa sem nome'}
                                                <p>{tenantForm.document || 'Documento nao informado'} | {tenantForm.support_email || tenantForm.support_phone || 'Suporte nao informado'}</p>
                                            </td>
                                            <td><span className={`status ${tenantForm.active ? 'ready' : 'cancelled'}`}>{tenantForm.active ? 'Ativa' : 'Inativa'}</span></td>
                                            <td><button className="secondary-button company-view-button" type="button" onClick={() => setSettingsFormOpen(true)}>Editar</button></td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Loja publica</strong>
                                                <p>Link, tema, banner e vitrine</p>
                                            </td>
                                            <td>
                                                {tenant?.store_url || `/loja/${tenantForm.store_slug}`}
                                                <p>{tenantForm.store_title || 'Titulo nao informado'} | {storeThemeOptions.find(([value]) => value === tenantForm.store_theme)?.[1] || tenantForm.store_theme}</p>
                                            </td>
                                            <td><span className="status ready">Publicada</span></td>
                                            <td>
                                                <div className="row-actions">
                                                    {tenant?.store_url && <a className="icon-link" href={tenant.store_url} target="_blank" rel="noreferrer" title="Abrir loja"><ExternalLink size={17} /></a>}
                                                    <button className="secondary-button company-view-button" type="button" onClick={() => setSettingsFormOpen(true)}>Editar</button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Frete</strong>
                                                <p>Regras de entrega por CEP</p>
                                            </td>
                                            <td>
                                                {tenantForm.store_shipping_regions.length} regra{tenantForm.store_shipping_regions.length === 1 ? '' : 's'} cadastrada{tenantForm.store_shipping_regions.length === 1 ? '' : 's'}
                                                <p>{tenantForm.store_shipping_regions.map((region) => region.region || 'Padrao').slice(0, 3).join(', ')}</p>
                                            </td>
                                            <td><span className="status ready">Configurado</span></td>
                                            <td><button className="secondary-button company-view-button" type="button" onClick={() => setSettingsFormOpen(true)}>Editar</button></td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Pagamentos</strong>
                                                <p>Gateways e credenciais</p>
                                            </td>
                                            <td>
                                                {tenantForm.payment_providers?.[tenantForm.active_payment_provider]?.label || tenantForm.active_payment_provider}
                                                <p>{Object.values(tenantForm.payment_providers || {}).filter((provider) => provider.enabled).length} gateway(s) habilitado(s)</p>
                                            </td>
                                            <td><span className={`status ${settings.configured ? 'ready' : 'draft'}`}>{settings.configured ? 'Ativo' : 'Pendente'}</span></td>
                                            <td><button className="secondary-button company-view-button" type="button" onClick={() => setSettingsFormOpen(true)}>Editar</button></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                        {settingsFormOpen && (
                        <div className="storefront-modal-backdrop admin-modal-backdrop" onClick={() => setSettingsFormOpen(false)}>
                        <div className="settings-modal-stack" onClick={(event) => event.stopPropagation()}>
                        <form className="panel settings-panel tenant-panel" onSubmit={submitTenant}>
                            <div className="panel-title">
                                <Settings size={20} />
                                <h2>Editar configuracoes da empresa</h2>
                                <button className="icon-button" type="button" title="Fechar" onClick={() => setSettingsFormOpen(false)}>
                                    <X size={17} />
                                </button>
                            </div>
                            <div className="settings-grid tenant-grid">
                                <Field label="Nome da empresa">
                                    <input value={tenantForm.name} onChange={(event) => {
                                        const name = event.target.value;
                                        setTenantForm({
                                            ...tenantForm,
                                            name,
                                            store_title: tenantForm.store_title || name,
                                            store_slug: tenantForm.store_slug || slugify(name),
                                        });
                                    }} required />
                                </Field>
                                <Field label="Documento">
                                    <input value={tenantForm.document} onChange={(event) => setTenantForm({ ...tenantForm, document: event.target.value })} placeholder="CNPJ ou CPF" />
                                </Field>
                                <Field label="Telefone suporte">
                                    <input value={tenantForm.support_phone} onChange={(event) => setTenantForm({ ...tenantForm, support_phone: event.target.value })} />
                                </Field>
                                <Field label="E-mail suporte">
                                    <input type="email" value={tenantForm.support_email} onChange={(event) => setTenantForm({ ...tenantForm, support_email: event.target.value })} />
                                </Field>
                            </div>

                            <div className="theme-grid">
                                <Field label="Cor menu/admin">
                                    <input type="color" value={tenantForm.admin_primary_color} onChange={(event) => setTenantForm({ ...tenantForm, admin_primary_color: event.target.value })} />
                                </Field>
                                <Field label="Cor destaque/admin">
                                    <input type="color" value={tenantForm.admin_accent_color} onChange={(event) => setTenantForm({ ...tenantForm, admin_accent_color: event.target.value })} />
                                </Field>
                                <Field label="Cor checkout">
                                    <input type="color" value={tenantForm.checkout_primary_color} onChange={(event) => setTenantForm({ ...tenantForm, checkout_primary_color: event.target.value })} />
                                </Field>
                                <Field label="Botao checkout">
                                    <input type="color" value={tenantForm.checkout_button_color} onChange={(event) => setTenantForm({ ...tenantForm, checkout_button_color: event.target.value })} />
                                </Field>
                            </div>

                            <div className="company-form-section">
                                <div className="form-section-title">
                                    <strong>Loja web</strong>
                                    <span>Configure a vitrine publica desta empresa para vender os produtos cadastrados.</span>
                                </div>
                                <div className="store-settings-grid">
                                    <Field label="Link da loja">
                                        <input
                                            value={tenantForm.store_slug}
                                            onChange={(event) => setTenantForm({ ...tenantForm, store_slug: slugify(event.target.value) })}
                                            required
                                        />
                                    </Field>
                                    <Field label="Tema">
                                        <select value={tenantForm.store_theme} onChange={(event) => setTenantForm({ ...tenantForm, store_theme: event.target.value })}>
                                            {storeThemeOptions.map(([value, label]) => <option value={value} key={value}>{label}</option>)}
                                            {storeThemes.length > 0 && <option disabled>-- Temas personalizados --</option>}
                                            {storeThemes.filter((theme) => theme.active).map((theme) => <option value={theme.slug} key={theme.id}>{theme.name}</option>)}
                                        </select>
                                    </Field>
                                    <Field label="Titulo da loja">
                                        <input value={tenantForm.store_title} onChange={(event) => setTenantForm({ ...tenantForm, store_title: event.target.value })} required />
                                    </Field>
                                    <Field label="Selo/chamada">
                                        <input value={tenantForm.store_banner_label} onChange={(event) => setTenantForm({ ...tenantForm, store_banner_label: event.target.value })} />
                                    </Field>
                                    <Field label="Subtitulo">
                                        <textarea value={tenantForm.store_subtitle} onChange={(event) => setTenantForm({ ...tenantForm, store_subtitle: event.target.value })} rows="3" />
                                    </Field>
                                    <Field label="Imagem do banner (URL)">
                                        <input value={tenantForm.store_banner_image_url} onChange={(event) => setTenantForm({ ...tenantForm, store_banner_image_url: event.target.value })} placeholder="https://..." />
                                        <input type="file" accept="image/*" onChange={(event) => uploadStorefrontImage(event.target.files?.[0], (url) => setTenantForm((current) => ({ ...current, store_banner_image_url: url })))} />
                                    </Field>
                                    <Field label="Imagem destaque lateral (URL)">
                                        <input value={tenantForm.store_featured_image_url} onChange={(event) => setTenantForm({ ...tenantForm, store_featured_image_url: event.target.value })} placeholder="https://..." />
                                        <input type="file" accept="image/*" onChange={(event) => uploadStorefrontImage(event.target.files?.[0], (url) => setTenantForm((current) => ({ ...current, store_featured_image_url: url })))} />
                                    </Field>
                                    <Field label="Selo destaque lateral">
                                        <input value={tenantForm.store_featured_label} onChange={(event) => setTenantForm({ ...tenantForm, store_featured_label: event.target.value })} placeholder="Ofertas do dia" />
                                    </Field>
                                    <Field label="Titulo destaque lateral">
                                        <input value={tenantForm.store_featured_title} onChange={(event) => setTenantForm({ ...tenantForm, store_featured_title: event.target.value })} placeholder="Ate 20% OFF" />
                                    </Field>
                                    <Field label="Texto destaque lateral">
                                        <input value={tenantForm.store_featured_subtitle} onChange={(event) => setTenantForm({ ...tenantForm, store_featured_subtitle: event.target.value })} placeholder="Em itens selecionados" />
                                    </Field>
                                    <Field label="Botao destaque lateral">
                                        <input value={tenantForm.store_featured_cta} onChange={(event) => setTenantForm({ ...tenantForm, store_featured_cta: event.target.value })} placeholder="Ver" />
                                    </Field>
                                    <Field label="Imagem Compra segura (URL)">
                                        <input value={tenantForm.store_secure_image_url} onChange={(event) => setTenantForm({ ...tenantForm, store_secure_image_url: event.target.value })} placeholder="https://..." />
                                        <input type="file" accept="image/*" onChange={(event) => uploadStorefrontImage(event.target.files?.[0], (url) => setTenantForm((current) => ({ ...current, store_secure_image_url: url })))} />
                                    </Field>
                                    <Field label="Selo compra segura">
                                        <input value={tenantForm.store_secure_label} onChange={(event) => setTenantForm({ ...tenantForm, store_secure_label: event.target.value })} placeholder="Protecao" />
                                    </Field>
                                    <Field label="Titulo compra segura">
                                        <input value={tenantForm.store_secure_title} onChange={(event) => setTenantForm({ ...tenantForm, store_secure_title: event.target.value })} placeholder="Compra segura" />
                                    </Field>
                                    <Field label="Texto compra segura">
                                        <input value={tenantForm.store_secure_subtitle} onChange={(event) => setTenantForm({ ...tenantForm, store_secure_subtitle: event.target.value })} placeholder="Pix e acompanhamento pelo painel" />
                                    </Field>
                                    <Field label="Botao compra segura">
                                        <input value={tenantForm.store_secure_cta} onChange={(event) => setTenantForm({ ...tenantForm, store_secure_cta: event.target.value })} placeholder="Ver" />
                                    </Field>
                                    {tenant?.store_url && (
                                        <a className="secondary-button store-open-link" href={tenant.store_url} target="_blank" rel="noreferrer">
                                            <ExternalLink size={17} />
                                            Abrir loja
                                        </a>
                                    )}
                                </div>
                                <div className="theme-builder">
                                    <div className="form-section-title compact-copy">
                                        <strong>Temas personalizados</strong>
                                        <span>Crie campanhas como Copa do Mundo, aniversario da loja ou promocao local.</span>
                                    </div>
                                    <div className="users-table-wrap">
                                        <table className="users-table admin-data-table theme-builder-table">
                                            <thead>
                                                <tr>
                                                    <th>Tema</th>
                                                    <th>Cores</th>
                                                    <th>Status</th>
                                                    <th>Acoes</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {storeThemes.map((theme) => (
                                                    <tr key={theme.id}>
                                                        <td>
                                                            <strong>{theme.name}</strong>
                                                            <p>{theme.slug}</p>
                                                        </td>
                                                        <td>
                                                            <span className="theme-color-swatches">
                                                                <i style={{ background: theme.primary_color }} />
                                                                <i style={{ background: theme.accent_color }} />
                                                                <i style={{ background: theme.background_color }} />
                                                            </span>
                                                        </td>
                                                        <td><span className={`status ${theme.active ? 'ready' : 'cancelled'}`}>{theme.active ? 'Ativo' : 'Inativo'}</span></td>
                                                        <td>
                                                            <div className="row-actions">
                                                                <button className="icon-button" type="button" title="Editar tema" onClick={() => editStoreTheme(theme)}><Pencil size={17} /></button>
                                                                <button className="icon-button danger-action" type="button" title="Excluir tema" onClick={() => deleteStoreTheme(theme)}><Trash2 size={17} /></button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                        {!storeThemes.length && <div className="empty-state">Nenhum tema personalizado cadastrado.</div>}
                                    </div>
                                    <form className="theme-builder-form" onSubmit={submitStoreTheme}>
                                        <Field label="Nome do tema">
                                            <input value={themeForm.name} onChange={(event) => setThemeForm({ ...themeForm, name: event.target.value, slug: themeForm.slug || slugify(event.target.value) })} placeholder="Copa do Mundo" required />
                                        </Field>
                                        <Field label="Slug">
                                            <input value={themeForm.slug} onChange={(event) => setThemeForm({ ...themeForm, slug: slugify(event.target.value) })} placeholder="copa-do-mundo" />
                                        </Field>
                                        <Field label="Cor principal">
                                            <input type="color" value={themeForm.primary_color} onChange={(event) => setThemeForm({ ...themeForm, primary_color: event.target.value })} />
                                        </Field>
                                        <Field label="Cor destaque">
                                            <input type="color" value={themeForm.accent_color} onChange={(event) => setThemeForm({ ...themeForm, accent_color: event.target.value })} />
                                        </Field>
                                        <Field label="Fundo">
                                            <input type="color" value={themeForm.background_color} onChange={(event) => setThemeForm({ ...themeForm, background_color: event.target.value })} />
                                        </Field>
                                        <Field label="Selo do banner">
                                            <input value={themeForm.banner_label} onChange={(event) => setThemeForm({ ...themeForm, banner_label: event.target.value })} placeholder="Copa do Mundo" />
                                        </Field>
                                        <Field label="Imagem banner">
                                            <input value={themeForm.banner_image_url} onChange={(event) => setThemeForm({ ...themeForm, banner_image_url: event.target.value })} placeholder="https://..." />
                                        </Field>
                                        <Field label="Imagem destaque">
                                            <input value={themeForm.featured_image_url} onChange={(event) => setThemeForm({ ...themeForm, featured_image_url: event.target.value })} placeholder="https://..." />
                                        </Field>
                                        <Field label="Titulo destaque">
                                            <input value={themeForm.featured_title} onChange={(event) => setThemeForm({ ...themeForm, featured_title: event.target.value })} placeholder="Ofertas campeas" />
                                        </Field>
                                        <Field label="Texto destaque">
                                            <input value={themeForm.featured_subtitle} onChange={(event) => setThemeForm({ ...themeForm, featured_subtitle: event.target.value })} placeholder="Promocao especial da campanha" />
                                        </Field>
                                        <label className="toggle inline-toggle">
                                            <input type="checkbox" checked={themeForm.active} onChange={(event) => setThemeForm({ ...themeForm, active: event.target.checked })} />
                                            <span>Tema ativo</span>
                                        </label>
                                        <div className="theme-builder-actions">
                                            <button className="secondary-button" type="button" onClick={resetThemeForm}>Limpar</button>
                                            <button className="primary-button">{themeForm.id ? 'Atualizar tema' : 'Salvar tema'}</button>
                                        </div>
                                    </form>
                                </div>
                                <div className="shipping-region-editor">
                                    <div className="form-section-title compact-copy">
                                        <strong>Frete por regiao</strong>
                                        <span>O checkout calcula pelo prefixo do CEP. Deixe o prefixo vazio para retirada, digital ou padrao.</span>
                                    </div>
                                    {tenantForm.store_shipping_regions.map((region, index) => (
                                        <div className="shipping-region-row" key={`${region.region}-${index}`}>
                                            <input value={region.region} onChange={(event) => updateTenantShippingRegion(index, { region: event.target.value })} placeholder="Regiao" />
                                            <input value={region.cep_prefix} onChange={(event) => updateTenantShippingRegion(index, { cep_prefix: event.target.value.replace(/\D/g, '') })} placeholder="Prefixo CEP" />
                                            <input type="number" min="0" step="0.01" value={region.price} onChange={(event) => updateTenantShippingRegion(index, { price: event.target.value })} placeholder="Valor" />
                                            <input value={region.eta} onChange={(event) => updateTenantShippingRegion(index, { eta: event.target.value })} placeholder="Prazo" />
                                        </div>
                                    ))}
                                    <button className="secondary-button compact-secondary" type="button" onClick={addTenantShippingRegion}>
                                        <Plus size={16} />
                                        Adicionar regiao
                                    </button>
                                </div>
                            </div>

                            <div className="payment-provider-panel">
                                <div className="form-section-title">
                                    <strong>Metodo de pagamento principal</strong>
                                    <span>Mercado Pago ja gera Pix. Os demais ficam preparados para integracao.</span>
                                </div>
                                <div className="users-table-wrap">
                                    <table className="users-table admin-data-table payment-provider-table">
                                        <thead>
                                            <tr>
                                                <th>Gateway</th>
                                                <th>Credenciais</th>
                                                <th>Principal</th>
                                                <th>Status</th>
                                                <th>Ativo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {Object.entries(tenantForm.payment_providers || {}).map(([provider, config]) => (
                                                <tr className={tenantForm.active_payment_provider === provider ? 'selected' : ''} key={provider}>
                                                    <td>
                                                        <strong>{config.label}</strong>
                                                        <p>{config.configured ? 'Credenciais configuradas' : provider === 'mercado_pago' ? 'Configure em Mercado Pago' : 'Aguardando integracao'}</p>
                                                    </td>
                                                    <td>
                                                        <div className="provider-credentials">
                                                            {(config.credential_fields || []).map((field) => (
                                                                <input
                                                                    key={field}
                                                                    type={field.includes('secret') || field.includes('token') || field.includes('key') ? 'password' : 'text'}
                                                                    placeholder={field.replaceAll('_', ' ')}
                                                                    value={tenantForm.payment_credentials?.[provider]?.[field] || ''}
                                                                    onChange={(event) => updateTenantCredential(provider, field, event.target.value)}
                                                                />
                                                            ))}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <input
                                                            type="radio"
                                                            name="active_payment_provider"
                                                            checked={tenantForm.active_payment_provider === provider}
                                                            onChange={() => setTenantForm({ ...tenantForm, active_payment_provider: provider })}
                                                        />
                                                    </td>
                                                    <td><span className={`status ${config.configured ? 'ready' : 'draft'}`}>{config.configured ? 'Configurado' : 'Pendente'}</span></td>
                                                    <td>
                                                        <input
                                                            type="checkbox"
                                                            checked={Boolean(config.enabled)}
                                                            onChange={(event) => updateProviderEnabled(provider, event.target.checked)}
                                                        />
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <button className="secondary-button" disabled={savingTenant}>
                                {savingTenant ? 'Salvando...' : 'Salvar empresa e tema'}
                            </button>
                        </form>

                        <form className="panel settings-panel" onSubmit={submitSettings}>
                            <div className="panel-title">
                                <Settings size={20} />
                                <h2>Mercado Pago</h2>
                                <span className={`status ${settings.configured ? 'ready' : 'draft'}`}>
                                    {settings.configured ? 'Ativo' : 'Falta token'}
                                </span>
                            </div>
                            <div className="settings-grid">
                                <Field label="Access token">
                                    <input type="password" value={settingsForm.access_token} onChange={(event) => setSettingsForm({ ...settingsForm, access_token: event.target.value })} placeholder={settings.configured ? 'Token salvo. Preencha apenas para trocar.' : 'APP_USR... ou TEST...'} autoComplete="off" />
                                </Field>
                                <Field label="Public key">
                                    <input value={settingsForm.public_key} onChange={(event) => setSettingsForm({ ...settingsForm, public_key: event.target.value })} placeholder="APP_USR... ou TEST..." />
                                </Field>
                                <Field label="Nome na fatura">
                                    <input maxLength="22" value={settingsForm.statement_descriptor} onChange={(event) => setSettingsForm({ ...settingsForm, statement_descriptor: event.target.value.toUpperCase() })} />
                                </Field>
                                <label className="toggle inline-toggle">
                                    <input type="checkbox" checked={settingsForm.sandbox} onChange={(event) => setSettingsForm({ ...settingsForm, sandbox: event.target.checked })} />
                                    <span>Modo sandbox/teste</span>
                                </label>
                            </div>
                            <button className="secondary-button" disabled={savingSettings}>
                                {savingSettings ? 'Salvando...' : 'Salvar configuracao'}
                            </button>
                        </form>
                        </div>
                        </div>
                        )}
                    </section>
                )}

                {activeSection === 'users' && (currentUser?.role === 'superadmin' || currentUser?.role === 'admin') && (
                    <section className="users-management-page">
                        <section className="panel user-list-panel">
                            <div className="users-table-toolbar">
                                <div className="panel-title-main">
                                    <Users size={20} />
                                    <div>
                                        <h2>Usuarios do sistema</h2>
                                        <p>{users.length} usuario{users.length === 1 ? '' : 's'} cadastrado{users.length === 1 ? '' : 's'}.</p>
                                    </div>
                                </div>
                                <span className="user-total-badge">{users.filter((user) => user.active).length} ativos</span>
                                <button className="primary-button user-create-button" type="button" onClick={openNewUserForm}>
                                    <Plus size={18} />
                                    Novo usuario
                                </button>
                            </div>
                            <div className="users-table-wrap">
                                <table className="users-table">
                                    <thead>
                                        <tr>
                                            <th>Usuario</th>
                                            <th>Empresa</th>
                                            <th>Papel</th>
                                            <th>Status</th>
                                            <th>Acoes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {users.map((user) => (
                                            <tr key={user.id}>
                                                <td>
                                                    <div className="user-identity table-user-identity">
                                                        <span className="user-avatar">{(user.name || user.email || 'U').slice(0, 1).toUpperCase()}</span>
                                                        <div>
                                                            <strong>{user.name}</strong>
                                                            <p>{user.email}</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>{user.tenant ? user.tenant.name : 'Plataforma / sem empresa'}</td>
                                                <td><span className="role-pill">{user.role === 'superadmin' ? 'Superadmin' : user.role === 'seller' ? 'Vendedor' : 'Admin'}</span></td>
                                                <td><span className={`status ${user.active ? 'ready' : 'cancelled'}`}>{user.active ? 'Ativo' : 'Inativo'}</span></td>
                                                <td>
                                                    <div className="row-actions user-actions">
                                                        <button className="icon-button" type="button" title="Editar usuario" onClick={() => editUser(user)}>
                                                            <Pencil size={17} />
                                                        </button>
                                                        <button className="icon-button danger-action" type="button" title="Excluir usuario" onClick={() => deleteUser(user)}>
                                                            <Trash2 size={17} />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                {!users.length && <div className="empty-state">Nenhum usuario cadastrado.</div>}
                            </div>
                        </section>

                        {userFormOpen && (
                            <div className="storefront-modal-backdrop user-modal-backdrop" onClick={resetUserForm}>
                                <form className="panel structured-panel user-form-panel user-form-modal" onSubmit={submitUser} onClick={(event) => event.stopPropagation()}>
                                    <div className="panel-title">
                                        <div className="panel-title-main">
                                            <Users size={20} />
                                            <div>
                                                <h2>{editingUserId ? 'Editar usuario' : 'Novo usuario'}</h2>
                                                <p>Controle quem acessa a empresa, painel e vendas.</p>
                                            </div>
                                        </div>
                                        <button className="icon-button" type="button" title="Fechar" onClick={resetUserForm}>
                                            <X size={17} />
                                        </button>
                                    </div>
                                    <div className="user-form-grid">
                                        <Field label="Nome">
                                            <input value={userForm.name} onChange={(event) => setUserForm({ ...userForm, name: event.target.value })} required />
                                        </Field>
                                        <Field label="E-mail">
                                            <input type="email" value={userForm.email} onChange={(event) => setUserForm({ ...userForm, email: event.target.value })} required />
                                        </Field>
                                        {currentUser?.role === 'superadmin' && (
                                            <Field label="Empresa">
                                                <select value={userForm.tenant_setting_id} onChange={(event) => setUserForm({ ...userForm, tenant_setting_id: event.target.value })}>
                                                    <option value="">Sem empresa / plataforma</option>
                                                    {companies.map((company) => (
                                                        <option value={company.id} key={company.id}>{company.name}</option>
                                                    ))}
                                                </select>
                                            </Field>
                                        )}
                                        <Field label={editingUserId ? 'Nova senha' : 'Senha'}>
                                            <input type="password" value={userForm.password} onChange={(event) => setUserForm({ ...userForm, password: event.target.value })} required={!editingUserId} minLength="6" />
                                        </Field>
                                        <Field label="Papel">
                                            <select value={userForm.role} onChange={(event) => setUserForm({ ...userForm, role: event.target.value })}>
                                                <option value="admin">Admin</option>
                                                <option value="seller">Vendedor</option>
                                                {currentUser?.role === 'superadmin' && <option value="superadmin">Superadmin</option>}
                                            </select>
                                        </Field>
                                    </div>
                                    <div className="user-form-footer">
                                        <label className="toggle inline-toggle">
                                            <input type="checkbox" checked={userForm.active} onChange={(event) => setUserForm({ ...userForm, active: event.target.checked })} />
                                            <span>Usuario ativo</span>
                                        </label>
                                        <button className="primary-button" disabled={savingUser}>
                                            <CheckCircle2 size={18} />
                                            {savingUser ? 'Salvando...' : editingUserId ? 'Atualizar usuario' : 'Criar usuario'}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        )}
                    </section>
                )}

                {activeSection === 'companies' && currentUser?.role === 'superadmin' && (
                    <section className="companies-page">
                        {companyFormOpen && (
                        <div className="storefront-modal-backdrop admin-modal-backdrop" onClick={resetCompanyForm}>
                        <form className="panel structured-panel company-form-panel admin-form-modal company-form-modal" onSubmit={submitCompany} onClick={(event) => event.stopPropagation()}>
                            <div className="panel-title">
                                <div className="panel-title-main">
                                    <Box size={20} />
                                    <div>
                                        <h2>{editingCompanyId ? 'Editar empresa' : 'Criar empresa'}</h2>
                                        <p>Cadastre empresas/clientes e defina o contexto visual e financeiro de cada uma.</p>
                                    </div>
                                </div>
                                <div className="panel-actions">
                                    {editingCompanyId && (
                                        <button className="secondary-button" type="button" onClick={resetCompanyForm}>
                                            Cancelar
                                        </button>
                                    )}
                                    <button className="primary-button compact-primary company-save-button" disabled={savingCompany}>
                                        <CheckCircle2 size={18} />
                                        {savingCompany ? 'Salvando...' : editingCompanyId ? 'Atualizar empresa' : 'Criar empresa'}
                                    </button>
                                </div>
                            </div>

                            <div className="company-form-section">
                                <div className="form-section-title">
                                    <strong>Dados da empresa</strong>
                                    <span>Essa é a entidade/tenant que define marca, checkout e gateway.</span>
                                </div>
                                <div className="company-fields-grid">
                                    <Field label="Nome da empresa">
                                        <input value={companyForm.name} onChange={(event) => {
                                            const name = event.target.value;
                                            setCompanyForm({
                                                ...companyForm,
                                                name,
                                                store_title: companyForm.store_title || name,
                                                store_slug: companyForm.store_slug || slugify(name),
                                            });
                                        }} required />
                                    </Field>
                                    <Field label="Documento">
                                        <input value={companyForm.document} onChange={(event) => setCompanyForm({ ...companyForm, document: event.target.value })} />
                                    </Field>
                                    <Field label="E-mail suporte">
                                        <input type="email" value={companyForm.support_email} onChange={(event) => setCompanyForm({ ...companyForm, support_email: event.target.value })} />
                                    </Field>
                                    <Field label="Telefone suporte">
                                        <input value={companyForm.support_phone} onChange={(event) => setCompanyForm({ ...companyForm, support_phone: event.target.value })} />
                                    </Field>
                                </div>
                            </div>

                            <div className="company-form-section">
                                <div className="form-section-title">
                                    <strong>Identidade visual</strong>
                                    <span>Essas cores alteram o painel administrativo e o checkout público.</span>
                                </div>
                                <div className="theme-grid company-theme-grid">
                                    <Field label="Menu/admin">
                                        <input type="color" value={companyForm.admin_primary_color} onChange={(event) => setCompanyForm({ ...companyForm, admin_primary_color: event.target.value })} />
                                    </Field>
                                    <Field label="Destaque/admin">
                                        <input type="color" value={companyForm.admin_accent_color} onChange={(event) => setCompanyForm({ ...companyForm, admin_accent_color: event.target.value })} />
                                    </Field>
                                    <Field label="Checkout">
                                        <input type="color" value={companyForm.checkout_primary_color} onChange={(event) => setCompanyForm({ ...companyForm, checkout_primary_color: event.target.value })} />
                                    </Field>
                                    <Field label="Botao checkout">
                                        <input type="color" value={companyForm.checkout_button_color} onChange={(event) => setCompanyForm({ ...companyForm, checkout_button_color: event.target.value })} />
                                    </Field>
                                </div>
                            </div>

                            <div className="company-form-section">
                                <div className="form-section-title">
                                    <strong>Loja web</strong>
                                    <span>Pagina publica da empresa com os produtos ativos para compra direta.</span>
                                </div>
                                <div className="store-settings-grid">
                                    <Field label="Slug da loja">
                                        <input
                                            value={companyForm.store_slug}
                                            onChange={(event) => setCompanyForm({ ...companyForm, store_slug: slugify(event.target.value) })}
                                            placeholder="empresa-exemplo"
                                            required
                                        />
                                    </Field>
                                    <Field label="Tema">
                                        <select value={companyForm.store_theme} onChange={(event) => setCompanyForm({ ...companyForm, store_theme: event.target.value })}>
                                            {storeThemeOptions.map(([value, label]) => <option value={value} key={value}>{label}</option>)}
                                        </select>
                                    </Field>
                                    <Field label="Titulo">
                                        <input value={companyForm.store_title} onChange={(event) => setCompanyForm({ ...companyForm, store_title: event.target.value })} required />
                                    </Field>
                                    <Field label="Selo da campanha">
                                        <input value={companyForm.store_banner_label} onChange={(event) => setCompanyForm({ ...companyForm, store_banner_label: event.target.value })} placeholder="Dia dos Pais" />
                                    </Field>
                                    <Field label="Subtitulo da vitrine">
                                        <textarea value={companyForm.store_subtitle} onChange={(event) => setCompanyForm({ ...companyForm, store_subtitle: event.target.value })} rows="3" />
                                    </Field>
                                    <Field label="Imagem do banner (URL)">
                                        <input value={companyForm.store_banner_image_url} onChange={(event) => setCompanyForm({ ...companyForm, store_banner_image_url: event.target.value })} placeholder="https://..." />
                                        <input type="file" accept="image/*" onChange={(event) => uploadStorefrontImage(event.target.files?.[0], (url) => setCompanyForm((current) => ({ ...current, store_banner_image_url: url })))} />
                                    </Field>
                                    <Field label="Imagem destaque lateral (URL)">
                                        <input value={companyForm.store_featured_image_url} onChange={(event) => setCompanyForm({ ...companyForm, store_featured_image_url: event.target.value })} placeholder="https://..." />
                                        <input type="file" accept="image/*" onChange={(event) => uploadStorefrontImage(event.target.files?.[0], (url) => setCompanyForm((current) => ({ ...current, store_featured_image_url: url })))} />
                                    </Field>
                                    <Field label="Selo destaque lateral">
                                        <input value={companyForm.store_featured_label} onChange={(event) => setCompanyForm({ ...companyForm, store_featured_label: event.target.value })} placeholder="Ofertas do dia" />
                                    </Field>
                                    <Field label="Titulo destaque lateral">
                                        <input value={companyForm.store_featured_title} onChange={(event) => setCompanyForm({ ...companyForm, store_featured_title: event.target.value })} placeholder="Ate 20% OFF" />
                                    </Field>
                                    <Field label="Texto destaque lateral">
                                        <input value={companyForm.store_featured_subtitle} onChange={(event) => setCompanyForm({ ...companyForm, store_featured_subtitle: event.target.value })} placeholder="Em itens selecionados" />
                                    </Field>
                                    <Field label="Botao destaque lateral">
                                        <input value={companyForm.store_featured_cta} onChange={(event) => setCompanyForm({ ...companyForm, store_featured_cta: event.target.value })} placeholder="Ver" />
                                    </Field>
                                    <Field label="Imagem Compra segura (URL)">
                                        <input value={companyForm.store_secure_image_url} onChange={(event) => setCompanyForm({ ...companyForm, store_secure_image_url: event.target.value })} placeholder="https://..." />
                                        <input type="file" accept="image/*" onChange={(event) => uploadStorefrontImage(event.target.files?.[0], (url) => setCompanyForm((current) => ({ ...current, store_secure_image_url: url })))} />
                                    </Field>
                                    <Field label="Selo compra segura">
                                        <input value={companyForm.store_secure_label} onChange={(event) => setCompanyForm({ ...companyForm, store_secure_label: event.target.value })} placeholder="Protecao" />
                                    </Field>
                                    <Field label="Titulo compra segura">
                                        <input value={companyForm.store_secure_title} onChange={(event) => setCompanyForm({ ...companyForm, store_secure_title: event.target.value })} placeholder="Compra segura" />
                                    </Field>
                                    <Field label="Texto compra segura">
                                        <input value={companyForm.store_secure_subtitle} onChange={(event) => setCompanyForm({ ...companyForm, store_secure_subtitle: event.target.value })} placeholder="Pix e acompanhamento pelo painel" />
                                    </Field>
                                    <Field label="Botao compra segura">
                                        <input value={companyForm.store_secure_cta} onChange={(event) => setCompanyForm({ ...companyForm, store_secure_cta: event.target.value })} placeholder="Ver" />
                                    </Field>
                                </div>
                                <div className="shipping-region-editor">
                                    <div className="form-section-title compact-copy">
                                        <strong>Frete por regiao</strong>
                                        <span>Use prefixos de CEP para calcular entrega no checkout.</span>
                                    </div>
                                    {companyForm.store_shipping_regions.map((region, index) => (
                                        <div className="shipping-region-row" key={`${region.region}-${index}`}>
                                            <input value={region.region} onChange={(event) => updateCompanyShippingRegion(index, { region: event.target.value })} placeholder="Regiao" />
                                            <input value={region.cep_prefix} onChange={(event) => updateCompanyShippingRegion(index, { cep_prefix: event.target.value.replace(/\D/g, '') })} placeholder="Prefixo CEP" />
                                            <input type="number" min="0" step="0.01" value={region.price} onChange={(event) => updateCompanyShippingRegion(index, { price: event.target.value })} placeholder="Valor" />
                                            <input value={region.eta} onChange={(event) => updateCompanyShippingRegion(index, { eta: event.target.value })} placeholder="Prazo" />
                                        </div>
                                    ))}
                                    <button className="secondary-button compact-secondary" type="button" onClick={addCompanyShippingRegion}>
                                        <Plus size={16} />
                                        Adicionar regiao
                                    </button>
                                </div>
                            </div>

                            <div className="company-form-section">
                                <div className="form-section-title">
                                    <strong>Gateways de pagamento</strong>
                                    <span>Escolha o gateway principal da empresa e quais ficam habilitados.</span>
                                </div>
                                <div className="company-provider-list">
                                    {Object.entries(companyForm.payment_providers || {}).map(([provider, config]) => (
                                        <label className={`company-provider-row ${companyForm.active_payment_provider === provider ? 'selected' : ''}`} key={provider}>
                                            <input
                                                type="radio"
                                                name="company_active_payment_provider"
                                                checked={companyForm.active_payment_provider === provider}
                                                onChange={() => setCompanyForm({ ...companyForm, active_payment_provider: provider })}
                                            />
                                            <div>
                                                <strong>{config.label}</strong>
                                                <span>{config.configured ? 'Configurado' : provider === 'mercado_pago' ? 'Pix já disponível quando configurado' : 'Preparado para integração'}</span>
                                            </div>
                                            <div className="provider-credentials">
                                                {(config.credential_fields || []).map((field) => (
                                                    <input
                                                        key={field}
                                                        type={field.includes('secret') || field.includes('token') || field.includes('key') ? 'password' : 'text'}
                                                        placeholder={field.replaceAll('_', ' ')}
                                                        value={companyForm.payment_credentials?.[provider]?.[field] || ''}
                                                        onChange={(event) => updateCompanyCredential(provider, field, event.target.value)}
                                                    />
                                                ))}
                                            </div>
                                            <span className="provider-main-badge">{companyForm.active_payment_provider === provider ? 'Principal' : 'Opcional'}</span>
                                            <input
                                                type="checkbox"
                                                checked={Boolean(config.enabled)}
                                                onChange={(event) => updateCompanyProviderEnabled(provider, event.target.checked)}
                                            />
                                        </label>
                                    ))}
                                </div>
                            </div>
                        </form>
                        </div>
                        )}

                        <section className="panel company-list-panel">
                            <div className="users-table-toolbar">
                                <div className="panel-title-main">
                                    <Box size={20} />
                                    <div>
                                        <h2>Empresas cadastradas</h2>
                                        <p>Visualize, edite ou entre no contexto de uma empresa sem alterar as demais.</p>
                                    </div>
                                </div>
                                <span className="user-total-badge">{companies.length} empresas</span>
                                <button className="primary-button user-create-button" type="button" onClick={openNewCompanyForm}>
                                    <Plus size={18} />
                                    Nova empresa
                                </button>
                            </div>
                            <div className="users-table-wrap">
                                <table className="users-table admin-data-table">
                                    <thead>
                                        <tr>
                                            <th>Empresa</th>
                                            <th>Suporte</th>
                                            <th>Gateway</th>
                                            <th>Empresa</th>
                                            <th>Contexto</th>
                                            <th>Acoes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {companies.map((company) => (
                                            <tr key={company.id}>
                                                <td>
                                                    <strong>{company.name}</strong>
                                                    <p>{company.store_url || company.store_slug || 'Loja sem slug'}</p>
                                                </td>
                                                <td>{company.support_email || company.support_phone || company.document || 'Sem dados'}</td>
                                                <td><span className="role-pill">{company.payment_providers?.[company.active_payment_provider]?.label || company.active_payment_provider}</span></td>
                                                <td><span className={`status ${company.active ? 'ready' : 'cancelled'}`}>{company.active ? 'Ativa' : 'Inativa'}</span></td>
                                                <td><span className={`status ${company.is_current ? 'ready' : 'draft'}`}>{company.is_current ? 'Em contexto' : 'Fora do contexto'}</span></td>
                                                <td>
                                                    <div className="row-actions user-actions company-table-actions">
                                                        <button className="secondary-button company-view-button" type="button" title="Visualizar empresa" onClick={() => viewCompany(company)}>
                                                            Visualizar
                                                        </button>
                                                        <button className="secondary-button company-view-button" type="button" title={company.active ? 'Desativar empresa' : 'Ativar empresa'} onClick={() => toggleCompanyActive(company)}>
                                                            {company.active ? 'Desativar' : 'Ativar'}
                                                        </button>
                                                        {company.store_url && (
                                                            <a className="icon-link" title="Abrir loja" href={company.store_url} target="_blank" rel="noreferrer">
                                                                <ExternalLink size={17} />
                                                            </a>
                                                        )}
                                                        <button className="icon-button" type="button" title="Editar empresa" onClick={() => editCompany(company)}>
                                                            <Pencil size={17} />
                                                        </button>
                                                        <button className="icon-button danger-action" type="button" title="Excluir empresa" onClick={() => deleteCompany(company)}>
                                                            <Trash2 size={17} />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                {!companies.length && <div className="empty-state">Nenhuma empresa cadastrada.</div>}
                            </div>
                        </section>
                    </section>
                )}

                {activeSection === 'notifications' && canManageCompany && (
                    <form className="notifications-layout notifications-admin-page" onSubmit={submitNotifications}>
                        <section className="panel notification-list-panel">
                            <div className="users-table-toolbar">
                                <div className="panel-title-main">
                                    <Bell size={20} />
                                    <div>
                                        <h2>Notificacoes</h2>
                                        <p>Configure Evolution, contatos fixos, regras e historico de envios.</p>
                                    </div>
                                </div>
                                <span className="user-total-badge">{notificationForm.contacts.filter((contact) => contact.active).length} contatos ativos</span>
                                <button className="primary-button user-create-button" type="button" onClick={() => setNotificationsFormOpen(true)}>
                                    <Pencil size={18} />
                                    Editar
                                </button>
                            </div>
                            <div className="users-table-wrap">
                                <table className="users-table admin-data-table config-modules-table">
                                    <thead>
                                        <tr>
                                            <th>Modulo</th>
                                            <th>Resumo</th>
                                            <th>Status</th>
                                            <th>Acoes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <strong>Regras</strong>
                                                <p>Eventos que disparam mensagens</p>
                                            </td>
                                            <td>
                                                Venda iniciada: {notificationForm.notify_sale_created ? 'ativa' : 'inativa'}
                                                <p>Pagamento aprovado: {notificationForm.notify_payment_approved ? 'ativa' : 'inativa'}</p>
                                            </td>
                                            <td><span className={`status ${notificationForm.enabled ? 'ready' : 'draft'}`}>{notificationForm.enabled ? 'Envio ativo' : 'Envio parado'}</span></td>
                                            <td><button className="secondary-button company-view-button" type="button" onClick={() => setNotificationsFormOpen(true)}>Editar</button></td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Contatos fixos</strong>
                                                <p>Destinatarios internos</p>
                                            </td>
                                            <td>
                                                {notificationForm.contacts.filter((contact) => contact.active).length} ativo(s) de {notificationForm.contacts.length}
                                                <p>{notificationForm.dynamic_customer_enabled ? 'Tambem envia para o cliente da compra' : 'Nao envia para cliente dinamico'}</p>
                                            </td>
                                            <td><span className={`status ${notificationForm.contacts.some((contact) => contact.active) ? 'ready' : 'draft'}`}>{notificationForm.contacts.some((contact) => contact.active) ? 'Com contatos' : 'Sem contato'}</span></td>
                                            <td><button className="secondary-button company-view-button" type="button" onClick={() => setNotificationsFormOpen(true)}>Editar</button></td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Historico</strong>
                                                <p>Auditoria de envios</p>
                                            </td>
                                            <td>
                                                {notificationLogs.length} envio(s) registrado(s)
                                                <p>{notificationLogs[0] ? `Ultimo: ${notificationLogs[0].event}` : 'Nenhum envio ainda'}</p>
                                            </td>
                                            <td><span className="status ready">Disponivel</span></td>
                                            <td><button className="secondary-button company-view-button" type="button" onClick={() => setNotificationsFormOpen(true)}>Configurar</button></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                        {notificationsFormOpen && (
                        <div className="storefront-modal-backdrop admin-modal-backdrop" onClick={() => setNotificationsFormOpen(false)}>
                        <div className="settings-modal-stack" onClick={(event) => event.stopPropagation()}>
                        <section className="panel settings-panel">
                            <div className="panel-title">
                                <Bell size={20} />
                                <h2>Notificacoes da empresa</h2>
                                <button className="icon-button" type="button" title="Fechar" onClick={() => setNotificationsFormOpen(false)}>
                                    <X size={17} />
                                </button>
                            </div>
                            <div className="notification-switches">
                                <label className="toggle">
                                    <input type="checkbox" checked={notificationForm.enabled} onChange={(event) => setNotificationForm({ ...notificationForm, enabled: event.target.checked })} />
                                    <span>Ativar envios desta empresa</span>
                                </label>
                                <label className="toggle">
                                    <input type="checkbox" checked={notificationForm.dynamic_customer_enabled} onChange={(event) => setNotificationForm({ ...notificationForm, dynamic_customer_enabled: event.target.checked })} />
                                    <span>Modo dinamico: enviar tambem para o cliente da compra</span>
                                </label>
                                <label className="toggle">
                                    <input type="checkbox" checked={notificationForm.notify_sale_created} onChange={(event) => setNotificationForm({ ...notificationForm, notify_sale_created: event.target.checked })} />
                                    <span>Notificar venda iniciada</span>
                                </label>
                                <label className="toggle">
                                    <input type="checkbox" checked={notificationForm.notify_payment_approved} onChange={(event) => setNotificationForm({ ...notificationForm, notify_payment_approved: event.target.checked })} />
                                    <span>Notificar pagamento aprovado</span>
                                </label>
                            </div>
                        </section>

                        <section className="panel">
                            <div className="panel-title">
                                <Send size={20} />
                                <h2>Contatos fixos</h2>
                                <button className="icon-button" type="button" title="Adicionar contato" onClick={addNotificationContact}>
                                    <Plus size={17} />
                                </button>
                            </div>
                            <div className="notification-contacts">
                                <table className="admin-data-table notification-contact-table">
                                    <thead>
                                        <tr>
                                            <th>Nome</th>
                                            <th>WhatsApp</th>
                                            <th>Status</th>
                                            <th>Acoes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {notificationForm.contacts.map((contact, index) => (
                                            <tr key={contact.id || index}>
                                                <td>
                                                    <input value={contact.name} onChange={(event) => updateNotificationContact(index, 'name', event.target.value)} placeholder="Comercial" />
                                                </td>
                                                <td>
                                                    <input value={contact.phone} onChange={(event) => updateNotificationContact(index, 'phone', event.target.value)} placeholder="(11) 99999-9999" />
                                                </td>
                                                <td>
                                                    <label className="toggle inline-toggle">
                                                        <input type="checkbox" checked={contact.active} onChange={(event) => updateNotificationContact(index, 'active', event.target.checked)} />
                                                        <span>{contact.active ? 'Ativo' : 'Inativo'}</span>
                                                    </label>
                                                </td>
                                                <td>
                                                    <div className="row-actions">
                                                        <button className="icon-button" type="button" title="Testar envio" disabled={testingNotifications} onClick={() => testNotification(contact)}>
                                                            <Send size={17} />
                                                        </button>
                                                        <button className="icon-button danger-action" type="button" title="Remover contato" onClick={() => removeNotificationContact(index)}>
                                                            <Trash2 size={17} />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                {!notificationForm.contacts.length && <div className="empty-state">Nenhum contato fixo cadastrado.</div>}
                            </div>
                        </section>

                        <section className="panel notification-template-panel">
                            <div className="panel-title">
                                <MessageTemplateIcon />
                                <h2>Mensagens automaticas</h2>
                            </div>
                            <div className="template-grid">
                                <Field label="Venda iniciada">
                                    <textarea rows="6" value={notificationForm.sale_created_message} onChange={(event) => setNotificationForm({ ...notificationForm, sale_created_message: event.target.value })} />
                                </Field>
                                <Field label="Pagamento aprovado">
                                    <textarea rows="6" value={notificationForm.payment_approved_message} onChange={(event) => setNotificationForm({ ...notificationForm, payment_approved_message: event.target.value })} />
                                </Field>
                            </div>
                            <p className="helper-text">Variaveis: {'{cliente}'}, {'{email}'}, {'{telefone}'}, {'{cpf}'}, {'{produto}'}, {'{valor}'}, {'{status}'}, {'{pedido}'}, {'{pagamento}'}.</p>
                            <button className="primary-button notifications-save" disabled={savingNotifications}>
                                <CheckCircle2 size={18} />
                                {savingNotifications ? 'Salvando...' : 'Salvar notificacoes'}
                            </button>
                        </section>
                        </div>
                        </div>
                        )}

                        <section className="panel notification-log-panel">
                            <div className="panel-title">
                                <Activity size={20} />
                                <h2>Historico de envios</h2>
                            </div>
                            <div className="notification-logs">
                                {notificationLogs.map((log) => (
                                    <article className="notification-log-row" key={log.id}>
                                        <div>
                                            <strong>{log.event}</strong>
                                            <p>{log.recipient_name || log.recipient_type} - {log.recipient_phone}</p>
                                            {log.error_message && <small>{log.error_message}</small>}
                                        </div>
                                        <span className={`status ${log.status === 'sent' ? 'paid' : log.status === 'failed' ? 'cancelled' : 'pending'}`}>{log.status}</span>
                                    </article>
                                ))}
                                {!notificationLogs.length && <div className="empty-state">Nenhuma notificacao enviada ainda.</div>}
                            </div>
                        </section>
                    </form>
                )}
            </section>
        </main>
    );
}

function MessageTemplateIcon() {
    return <Bell size={20} />;
}

function PublicStorePage() {
    const storeSlug = window.location.pathname.split('/').filter(Boolean)[1] || '';
    const [store, setStore] = useState(null);
    const [error, setError] = useState('');
    const [session, setSession] = useState({ checked: false, authenticated: false, user: null });
    const [cart, setCart] = useState(() => {
        try {
            return JSON.parse(localStorage.getItem('storefront_cart') || '[]');
        } catch {
            return [];
        }
    });
    const [cartOpen, setCartOpen] = useState(false);
    const [selectedProduct, setSelectedProduct] = useState(null);
    const [selectedProductImage, setSelectedProductImage] = useState('');
    const [selectedVariant, setSelectedVariant] = useState({ size: '', color: '' });
    const [storeSearch, setStoreSearch] = useState(() => new URLSearchParams(window.location.search).get('busca') || '');
    const [customerAuthOpen, setCustomerAuthOpen] = useState(false);
    const [customerAccountTab, setCustomerAccountTab] = useState('profile');
    const [customerOrders, setCustomerOrders] = useState([]);
    const [customerOrdersLoading, setCustomerOrdersLoading] = useState(false);
    const [profileForm, setProfileForm] = useState({ name: '', email: '', phone: '', cpf: '', password: '' });
    const [addressForm, setAddressForm] = useState({ label: 'Principal', cep: '', street: '', number: '', complement: '', neighborhood: '', city: '', state: '', default: true });
    const [customerNotice, setCustomerNotice] = useState('');

    useEffect(() => {
        Promise.all([
            fetch('/auth/session', { headers: { Accept: 'application/json' } }).then((response) => response.json()),
            fetch(`/loja/${storeSlug}/data`, { headers: { Accept: 'application/json' } }).then((response) => {
                if (!response.ok) {
                    throw new Error('Loja indisponivel.');
                }

                return response.json();
            }),
        ])
            .then(([sessionData, data]) => {
                const customer = sessionData.customer || null;
                const sameTenant = customer && Number(customer.tenant_setting_id) === Number(data.tenant.id);
                setSession({ checked: true, authenticated: Boolean(sessionData.authenticated && sameTenant), user: sameTenant ? customer : null });
                setStore(data);
                document.documentElement.style.setProperty('--checkout-primary-color', data.tenant.custom_theme?.primary_color || data.tenant.checkout_primary_color || '#3b82f6');
                document.documentElement.style.setProperty('--checkout-button-color', data.tenant.custom_theme?.accent_color || data.tenant.checkout_button_color || '#43c97b');
                document.documentElement.style.setProperty('--store-background-color', data.tenant.custom_theme?.background_color || '#eef2f4');
            })
            .catch(() => setError('Esta loja nao esta disponivel no momento.'));
    }, [storeSlug]);

    useEffect(() => {
        localStorage.setItem('storefront_cart', JSON.stringify(cart));
    }, [cart]);

    useEffect(() => {
        const firstVariant = productVariants(selectedProduct)[0];
        const nextVariant = {
            size: firstVariant?.size || selectedProduct?.options?.sizes?.[0] || '',
            color: firstVariant?.color || selectedProduct?.options?.colors?.[0] || '',
        };
        const variantImage = findProductVariant(selectedProduct, nextVariant.size, nextVariant.color)?.image_url;
        setSelectedVariant(nextVariant);
        setSelectedProductImage(variantImage || productImageList(selectedProduct)[0] || '');
    }, [selectedProduct]);

    useEffect(() => {
        if (!session.user) return;
        setProfileForm({
            name: session.user.name || '',
            email: session.user.email || '',
            phone: session.user.phone || '',
            cpf: session.user.cpf || '',
            password: '',
        });
    }, [session.user]);

    useEffect(() => {
        if (customerAuthOpen && session.authenticated) {
            loadCustomerOrders();
        }
    }, [customerAuthOpen, session.authenticated]);

    function requireStoreLogin() {
        if (session.authenticated) {
            return true;
        }

        window.location.href = `/loja/${store?.tenant?.store_slug || storeSlug}/entrar?next=${encodeURIComponent(window.location.pathname + window.location.search)}`;
        return false;
    }

    async function reloadCustomerSession() {
        const response = await fetch('/customer/session', { headers: { Accept: 'application/json' } });
        const data = await response.json();
        const customer = data.customer || null;
        const sameTenant = customer && Number(customer.tenant_setting_id) === Number(store?.tenant?.id);
        setSession({ checked: true, authenticated: Boolean(data.authenticated && sameTenant), user: sameTenant ? customer : null });
    }

    async function loadCustomerOrders() {
        setCustomerOrdersLoading(true);
        try {
            const response = await fetch('/customer/orders', { headers: { Accept: 'application/json' } });
            setCustomerOrders(response.ok ? await response.json() : []);
        } finally {
            setCustomerOrdersLoading(false);
        }
    }

    async function submitCustomerProfile(event) {
        event.preventDefault();
        setCustomerNotice('');
        const response = await fetch('/customer/profile', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(profileForm),
        });

        if (!response.ok) {
            setCustomerNotice('Nao foi possivel atualizar seu perfil.');
            return;
        }

        await reloadCustomerSession();
        setCustomerNotice('Perfil atualizado.');
    }

    async function logoutCustomer() {
        await fetch('/customer/logout', {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '', 'X-Requested-With': 'XMLHttpRequest' },
        });
        setCustomerAuthOpen(false);
        setSession({ checked: true, authenticated: false, user: null });
        setCart([]);
    }

    async function submitCustomerAddress(event) {
        event.preventDefault();
        setCustomerNotice('');
        const response = await fetch('/customer/addresses', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ ...addressForm, state: addressForm.state.toUpperCase() }),
        });

        if (!response.ok) {
            setCustomerNotice('Revise o endereco informado.');
            return;
        }

        setAddressForm({ label: 'Principal', cep: '', street: '', number: '', complement: '', neighborhood: '', city: '', state: '', default: true });
        await reloadCustomerSession();
        setCustomerNotice('Endereco salvo.');
    }

    if (error) {
        return (
            <main className="result-shell">
                <section className="result-panel failure">
                    <CircleAlert size={42} />
                    <h1>Loja indisponivel</h1>
                    <p>{error}</p>
                </section>
            </main>
        );
    }

    if (!store) {
        return (
            <main className="result-shell">
                <section className="result-panel pending">
                    <Loader label="Carregando loja..." />
                </section>
            </main>
        );
    }

    const { tenant, products } = store;
    const activeStoreTheme = tenant.custom_theme || {};
    const categories = Object.entries(productTypeLabel)
        .map(([type, label]) => ({ type, label, count: products.filter((product) => product.type === type).length }))
        .filter((category) => category.count > 0);
    const normalizedSearch = storeSearch.trim().toLowerCase();
    const isSearchPage = /\/buscar$/.test(window.location.pathname);
    const filteredProducts = normalizedSearch
        ? products.filter((product) => [
            product.name,
            product.sku,
            product.description,
            productTypeLabel[product.type],
            billingCycleLabel[product.billing_cycle],
        ].some((value) => String(value || '').toLowerCase().includes(normalizedSearch)))
        : products;
    const categorizedProducts = categories.map((category) => ({
        ...category,
        products: products.filter((product) => product.type === category.type),
    }));
    const cartCount = cart.reduce((total, item) => total + item.quantity, 0);
    const cartTotal = cart.reduce((total, item) => total + ((item.product.final_amount_cents ?? item.product.price_cents) * item.quantity), 0);
    const featuredProducts = products.slice(0, 3);

    function productImageList(product) {
        return [...new Set([product?.image_url, ...(product?.gallery_urls || []), ...productVariants(product).map((variant) => variant.image_url)].filter(Boolean))];
    }

    function selectVariant(next) {
        const merged = { ...selectedVariant, ...next };
        const variant = findProductVariant(selectedProduct, merged.size, merged.color);
        setSelectedVariant(merged);
        if (variant?.image_url) {
            setSelectedProductImage(variant.image_url);
        }
    }

    function variantProduct(product) {
        const variant = findProductVariant(product, selectedVariant.size, selectedVariant.color);
        const price = variant?.price_cents ?? product.final_amount_cents ?? product.price_cents;

        return {
            ...product,
            final_amount_cents: price,
            price_cents: price,
            selected_size: selectedVariant.size,
            selected_color: selectedVariant.color,
            image_url: variant?.image_url || product.image_url,
        };
    }

    function orderDeliverySteps(order) {
        const status = order.delivery?.status || 'waiting_payment';
        const steps = order.delivery?.required
            ? ['waiting_payment', 'preparing', 'shipped', 'delivered']
            : ['waiting_payment', 'delivered'];
        const activeIndex = status === 'cancelled' ? 0 : Math.max(steps.indexOf(status), 0);

        return steps.map((step, index) => ({
            step,
            done: status !== 'cancelled' && index <= activeIndex,
        }));
    }

    function addToCart(product, quantity = 1) {
        if (!requireStoreLogin()) {
            return;
        }

        const productToAdd = selectedProduct?.id === product.id ? variantProduct(product) : product;
        const cartKey = `${productToAdd.id}-${productToAdd.selected_size || ''}-${productToAdd.selected_color || ''}`;
        setCart((current) => {
            const existing = current.find((item) => item.key === cartKey);

            if (existing) {
                return current.map((item) => item.key === cartKey
                    ? { ...item, quantity: Math.min(item.quantity + quantity, 99) }
                    : item);
            }

            return [...current, { key: cartKey, product: productToAdd, quantity }];
        });
        setCartOpen(true);
    }

    function updateCartQuantity(cartKey, quantity) {
        const nextQuantity = Math.max(Number(quantity || 1), 1);
        setCart((current) => current.map((item) => item.key === cartKey ? { ...item, quantity: nextQuantity } : item));
    }

    function removeFromCart(cartKey) {
        setCart((current) => current.filter((item) => item.key !== cartKey));
    }

    function submitStoreSearch(event) {
        event.preventDefault();
        const term = storeSearch.trim();
        const slug = store?.tenant?.store_slug || storeSlug;
        window.location.href = term ? `/loja/${slug}/buscar?busca=${encodeURIComponent(term)}` : `/loja/${slug}`;
    }

    function clearStoreSearch() {
        window.location.href = `/loja/${store?.tenant?.store_slug || storeSlug}`;
    }

    function renderProductCard(product) {
        const hasDiscount = (product.discount_amount_cents || 0) > 0;
        const productImages = productImageList(product);
        const coverImage = productImages[0] || '';

        return (
            <article className="storefront-product-card" key={product.id}>
                <button className={`storefront-product-image ${coverImage ? 'has-image' : ''}`} type="button" onClick={() => setSelectedProduct(product)}>
                    {coverImage ? <img src={coverImage} alt={product.name} /> : <PackagePlus size={42} />}
                    <span className="storefront-product-badge-stack">
                        {hasDiscount && <em>Oferta</em>}
                        {productImages.length > 1 && <em>{productImages.length} fotos</em>}
                    </span>
                </button>
                <div>
                    <span className="storefront-product-type">{productTypeLabel[product.type] || 'Oferta'}</span>
                    <button className="storefront-product-title" type="button" onClick={() => setSelectedProduct(product)}>{product.name}</button>
                    <p>{product.description || billingCycleLabel[product.billing_cycle] || 'Contratacao online'}</p>
                    {(product.options?.sizes?.length || product.options?.colors?.length) && (
                        <small className="storefront-options">
                            {[...(product.options?.sizes || []), ...(product.options?.colors || [])].slice(0, 5).join(' / ')}
                        </small>
                    )}
                </div>
                <div className="storefront-price-block">
                    {hasDiscount && <small>{money(product.price_cents)}</small>}
                    <strong>{money(product.final_amount_cents ?? product.price_cents)}</strong>
                    <span>{billingCycleLabel[product.billing_cycle] || 'Pagamento unico'}</span>
                    <em>{product.requires_shipping ? 'Frete calculado por regiao' : 'Entrega digital ou ativacao rapida'}</em>
                </div>
                <div className="storefront-card-actions">
                    <button className="storefront-details-button" type="button" onClick={() => setSelectedProduct(product)}>Ver produto</button>
                    <button className="storefront-buy-button" type="button" onClick={() => addToCart(product)}>
                        Adicionar
                        <ShoppingCart size={17} />
                    </button>
                </div>
            </article>
        );
    }

    return (
        <main className={`storefront-page theme-${tenant.store_theme || 'default'}`}>
            <div className="storefront-top-strip">{activeStoreTheme.banner_label || tenant.store_banner_label || 'Oferta ativa na loja'}</div>
            <header className="storefront-hero">
                <nav className="storefront-nav">
                    <a className="storefront-brand" href="#produtos">
                        <span>{String(tenant.name || 'L').slice(0, 1)}</span>
                        <strong>{tenant.name}</strong>
                        <small>Loja oficial</small>
                    </a>
                    <form className="storefront-search" onSubmit={submitStoreSearch}>
                        <Search size={17} />
                        <input
                            placeholder={`Buscar em ${tenant.name}`}
                            value={storeSearch}
                            onChange={(event) => setStoreSearch(event.target.value)}
                        />
                    </form>
                    <div className="storefront-contact">
                        {tenant.support_phone && <span>{tenant.support_phone}</span>}
                        {tenant.support_email && <span>{tenant.support_email}</span>}
                        {session.authenticated ? (
                            <button className="storefront-account-button" type="button" onClick={() => setCustomerAuthOpen(true)}>
                                {session.user?.name}
                            </button>
                        ) : (
                            <a className="storefront-account-button" href={`/loja/${tenant.store_slug}/entrar`}>
                                Entrar / cadastrar
                            </a>
                        )}
                        <button className="storefront-cart-button" type="button" onClick={() => setCartOpen(true)}>
                            <ShoppingCart size={17} />
                            {cartCount}
                        </button>
                    </div>
                </nav>
                <div className="storefront-category-bar">
                    <span>Categorias</span>
                    {categories.map((category) => <a href={`#categoria-${category.type}`} key={category.type}>{category.label} ({category.count})</a>)}
                    <span className="storefront-delivery-hint">Compra segura | Entrega por regiao</span>
                </div>
                <section className="storefront-hero-grid">
                    <div className="storefront-hero-content" style={(activeStoreTheme.banner_image_url || tenant.store_banner_image_url) ? { backgroundImage: `linear-gradient(90deg, rgba(0,0,0,.62), rgba(0,0,0,.08)), url(${activeStoreTheme.banner_image_url || tenant.store_banner_image_url})` } : undefined}>
                        <span className="storefront-badge">{activeStoreTheme.banner_label || tenant.store_banner_label || 'Ofertas imperdiveis'}</span>
                        <h1>{tenant.store_title || tenant.name}</h1>
                        <p>{tenant.store_subtitle || 'Compre online com preco em destaque, Pix seguro e acompanhamento do pedido pela empresa.'}</p>
                        <a className="storefront-hero-button" href="#produtos">
                            <ShoppingCart size={18} />
                            Ver ofertas
                        </a>
                    </div>
                    <aside className="storefront-promo-stack">
                        <div style={(activeStoreTheme.featured_image_url || tenant.store_featured_image_url) ? { backgroundImage: `linear-gradient(90deg, rgba(0,0,0,.5), rgba(0,0,0,.05)), url(${activeStoreTheme.featured_image_url || tenant.store_featured_image_url})` } : undefined}>
                            <span>{tenant.store_featured_label || 'Ofertas do dia'}</span>
                            <strong>{activeStoreTheme.featured_title || tenant.store_featured_title || 'Ate 20% OFF'}</strong>
                            <p>{activeStoreTheme.featured_subtitle || tenant.store_featured_subtitle || 'Em itens selecionados'}</p>
                            <a href="#produtos">{tenant.store_featured_cta || 'Ver'}</a>
                        </div>
                        <div style={tenant.store_secure_image_url ? { backgroundImage: `linear-gradient(90deg, rgba(0,0,0,.5), rgba(0,0,0,.05)), url(${tenant.store_secure_image_url})` } : undefined}>
                            <span>{tenant.store_secure_label || 'Protecao'}</span>
                            <strong>{tenant.store_secure_title || 'Compra segura'}</strong>
                            <p>{tenant.store_secure_subtitle || 'Pix e acompanhamento pelo painel'}</p>
                            <a href="#produtos">{tenant.store_secure_cta || 'Ver'}</a>
                        </div>
                    </aside>
                </section>
            </header>

            {session.checked && !session.authenticated && (
                <section className="storefront-login-required">
                    <div>
                        <strong>Entre para comprar nesta loja</strong>
                        <p>Voce pode navegar pelas ofertas, mas precisa estar logado para adicionar ao carrinho e finalizar pedidos.</p>
                    </div>
                    <a href={`/loja/${tenant.store_slug}/entrar?next=${encodeURIComponent(window.location.pathname)}`}>Entrar na conta</a>
                </section>
            )}

            {customerAuthOpen && session.authenticated && (
                <div className="storefront-modal-backdrop" onClick={() => setCustomerAuthOpen(false)}>
                    <section className="customer-auth-modal" onClick={(event) => event.stopPropagation()}>
                        <button className="icon-button modal-close" type="button" onClick={() => setCustomerAuthOpen(false)}><X size={17} /></button>
                        <div className="customer-auth-head">
                            <strong>Minha conta</strong>
                            <p>Gerencie perfil, enderecos e pedidos nesta empresa.</p>
                        </div>
                        <div className="customer-account-tabs">
                            {[
                                ['profile', 'Perfil'],
                                ['addresses', 'Enderecos'],
                                ['orders', 'Pedidos'],
                            ].map(([tab, label]) => (
                                <button className={customerAccountTab === tab ? 'active' : ''} type="button" key={tab} onClick={() => setCustomerAccountTab(tab)}>
                                    {label}
                                </button>
                            ))}
                        </div>
                        {customerNotice && <div className="notice">{customerNotice}</div>}
                        <div className="customer-account-panel">
                            {customerAccountTab === 'profile' && (
                                <form className="customer-profile-form" onSubmit={submitCustomerProfile}>
                                    <Field label="Nome completo"><input value={profileForm.name} onChange={(event) => setProfileForm({ ...profileForm, name: event.target.value })} required /></Field>
                                    <Field label="E-mail"><input type="email" value={profileForm.email} onChange={(event) => setProfileForm({ ...profileForm, email: event.target.value })} required /></Field>
                                    <Field label="Telefone"><input value={profileForm.phone} onChange={(event) => setProfileForm({ ...profileForm, phone: event.target.value })} /></Field>
                                    <Field label="CPF"><input value={profileForm.cpf} onChange={(event) => setProfileForm({ ...profileForm, cpf: event.target.value })} /></Field>
                                    <Field label="Nova senha"><input type="password" value={profileForm.password} onChange={(event) => setProfileForm({ ...profileForm, password: event.target.value })} placeholder="Deixe em branco para manter" /></Field>
                                    <div className="customer-profile-actions">
                                        <button className="primary-button">Salvar perfil</button>
                                        <button className="secondary-button" type="button" onClick={logoutCustomer}>Sair da conta</button>
                                    </div>
                                </form>
                            )}
                            {customerAccountTab === 'addresses' && (
                                <>
                                    <div className="customer-address-list">
                                        {(session.user?.addresses || []).map((address) => (
                                            <article key={address.id}>
                                                <strong>{address.label || 'Endereco'}</strong>
                                                <span>{address.street}, {address.number} - {address.neighborhood}</span>
                                                <small>{address.city}/{address.state} - CEP {address.cep}</small>
                                            </article>
                                        ))}
                                        {!(session.user?.addresses || []).length && <div className="empty-state">Nenhum endereco cadastrado.</div>}
                                    </div>
                                    <form className="customer-address-form" onSubmit={submitCustomerAddress}>
                                        <Field label="Apelido"><input value={addressForm.label} onChange={(event) => setAddressForm({ ...addressForm, label: event.target.value })} /></Field>
                                        <Field label="CEP"><input value={addressForm.cep} onChange={(event) => setAddressForm({ ...addressForm, cep: event.target.value })} required /></Field>
                                        <Field label="Rua"><input value={addressForm.street} onChange={(event) => setAddressForm({ ...addressForm, street: event.target.value })} required /></Field>
                                        <Field label="Numero"><input value={addressForm.number} onChange={(event) => setAddressForm({ ...addressForm, number: event.target.value })} required /></Field>
                                        <Field label="Complemento"><input value={addressForm.complement} onChange={(event) => setAddressForm({ ...addressForm, complement: event.target.value })} /></Field>
                                        <Field label="Bairro"><input value={addressForm.neighborhood} onChange={(event) => setAddressForm({ ...addressForm, neighborhood: event.target.value })} required /></Field>
                                        <Field label="Cidade"><input value={addressForm.city} onChange={(event) => setAddressForm({ ...addressForm, city: event.target.value })} required /></Field>
                                        <Field label="UF"><input maxLength="2" value={addressForm.state} onChange={(event) => setAddressForm({ ...addressForm, state: event.target.value.toUpperCase() })} required /></Field>
                                        <button className="primary-button">Salvar endereco</button>
                                    </form>
                                </>
                            )}
                            {customerAccountTab === 'orders' && (
                                <div className="customer-orders-list">
                                    {customerOrdersLoading && <Loader label="Carregando pedidos..." />}
                                    {!customerOrdersLoading && customerOrders.map((order) => (
                                        <article className="customer-order-card" key={order.id}>
                                            <div className="customer-order-head">
                                                <div>
                                                    <span>Pedido #{order.public_id.slice(0, 8)}</span>
                                                    <strong>{order.product?.name || order.title}</strong>
                                                    <small>{formatDate(order.created_at)} | {money(order.final_amount_cents)}</small>
                                                </div>
                                                <span className={`status ${order.status}`}>{statusLabel[order.status] || order.status}</span>
                                            </div>
                                            <div className="customer-order-meta">
                                                <span>Pagamento: {paymentStatusLabel[order.payment?.status] || 'Aguardando'}</span>
                                                <span>{order.delivery?.required ? `Entrega: ${deliveryStatusLabel[order.delivery.status] || order.delivery.status}` : 'Entrega digital / ativacao'}</span>
                                            </div>
                                            {order.delivery?.required && (
                                                <div className="delivery-tracker">
                                                    {orderDeliverySteps(order).map((item) => (
                                                        <span className={item.done ? 'done' : ''} key={item.step}>{deliveryStatusLabel[item.step]}</span>
                                                    ))}
                                                    {order.delivery.region && <small>Regiao: {order.delivery.region}</small>}
                                                    {order.delivery.eta && <small>Prazo estimado: {order.delivery.eta}</small>}
                                                    {order.delivery.tracking_code && <small>Rastreamento: {order.delivery.tracking_code}</small>}
                                                    {order.delivery.tracking_url && <a href={order.delivery.tracking_url} target="_blank" rel="noreferrer">Abrir rastreio</a>}
                                                </div>
                                            )}
                                        </article>
                                    ))}
                                    {!customerOrdersLoading && !customerOrders.length && <div className="empty-state">Voce ainda nao tem pedidos nesta loja.</div>}
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            )}

            <section className="storefront-products-band" id="produtos">
                <div className="storefront-category-circles">
                    {categories.map((category) => (
                        <a href={`#categoria-${category.type}`} key={category.type}>
                            <span>{category.label.slice(0, 1)}</span>
                            <strong>{category.label}</strong>
                            <small>{category.count} ofertas</small>
                        </a>
                    ))}
                </div>
                {isSearchPage ? (
                    <section className="storefront-category-section">
                        <div className="storefront-section-title">
                            <div>
                                <span>Busca</span>
                                <h2>Resultados para "{storeSearch}"</h2>
                                <p>{filteredProducts.length ? 'Produtos encontrados nesta loja.' : 'Nenhum produto corresponde a sua busca.'}</p>
                            </div>
                            <button className="secondary-button storefront-clear-search" type="button" onClick={clearStoreSearch}>Limpar busca</button>
                        </div>
                        <div className="storefront-products">
                            {filteredProducts.map((product) => renderProductCard(product))}
                            {!filteredProducts.length && (
                                <div className="storefront-empty">
                                    <Box size={34} />
                                    <strong>Nenhum produto encontrado</strong>
                                    <p>Tente buscar por nome, categoria, SKU ou descricao.</p>
                                </div>
                            )}
                        </div>
                    </section>
                ) : (
                    categorizedProducts.map((category) => (
                        <section className="storefront-category-section" id={`categoria-${category.type}`} key={category.type}>
                            <div className="storefront-section-title">
                                <div>
                                    <span>{category.label}</span>
                                    <h2>{category.label}</h2>
                                    <p>{category.count} oferta{category.count === 1 ? '' : 's'} disponive{category.count === 1 ? 'l' : 'is'} nesta categoria.</p>
                                </div>
                                <strong>{category.count} ofertas</strong>
                            </div>
                            <div className="storefront-products">
                                {category.products.map((product) => renderProductCard(product))}
                            </div>
                        </section>
                    ))
                )}
            </section>

            <section className="storefront-info-sections">
                <article>
                    <CreditCard size={24} />
                    <strong>Pagamento Pix seguro</strong>
                    <p>O pedido gera pagamento e acompanha a confirmacao automaticamente no painel.</p>
                </article>
                <article>
                    <PackagePlus size={24} />
                    <strong>Entrega e ativacao</strong>
                    <p>Produtos fisicos usam frete por regiao. Planos e servicos seguem fluxo digital.</p>
                </article>
                <article>
                    <Bell size={24} />
                    <strong>Atendimento conectado</strong>
                    <p>A empresa acompanha status de venda e pode receber notificacoes pelo WhatsApp.</p>
                </article>
            </section>

            {featuredProducts.length > 0 && (
                <section className="storefront-feature-band">
                    <div>
                        <span>Curadoria</span>
                        <h2>Escolhas em destaque</h2>
                        <p>Produtos e planos configurados pela empresa para venda direta nesta loja.</p>
                    </div>
                    <div className="storefront-mini-grid">
                        {featuredProducts.map((product) => (
                            <button type="button" key={product.id} onClick={() => setSelectedProduct(product)}>
                                <strong>{product.name}</strong>
                                <span>{money(product.final_amount_cents ?? product.price_cents)}</span>
                            </button>
                        ))}
                    </div>
                </section>
            )}

            <footer className="storefront-footer">
                <div>
                    <strong>{tenant.name}</strong>
                    <p>{tenant.store_subtitle || 'Loja online com compra segura.'}</p>
                </div>
                <div>
                    {tenant.support_phone && <span>{tenant.support_phone}</span>}
                    {tenant.support_email && <span>{tenant.support_email}</span>}
                </div>
            </footer>

            {selectedProduct && (
                <div className="storefront-modal-backdrop" onClick={() => setSelectedProduct(null)}>
                    <section className="storefront-product-modal" onClick={(event) => event.stopPropagation()}>
                        <button className="icon-button modal-close" type="button" onClick={() => setSelectedProduct(null)}><X size={17} /></button>
                        <div className="storefront-modal-gallery">
                            <div className={`storefront-modal-image ${productImageList(selectedProduct).length ? 'has-image' : ''}`}>
                                {selectedProductImage ? <img src={selectedProductImage} alt={selectedProduct.name} /> : <PackagePlus size={52} />}
                            </div>
                            {productImageList(selectedProduct).length > 1 && (
                                <div className="storefront-modal-thumbs">
                                    {productImageList(selectedProduct).map((imageUrl, index) => (
                                        <button
                                            className={selectedProductImage === imageUrl ? 'active' : ''}
                                            type="button"
                                            key={`${imageUrl}-${index}`}
                                            onClick={() => setSelectedProductImage(imageUrl)}
                                        >
                                            <img src={imageUrl} alt={`${selectedProduct.name} ${index + 1}`} />
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                        <div className="storefront-modal-content">
                            <span className="storefront-product-type">{productTypeLabel[selectedProduct.type] || 'Oferta'}</span>
                            <h2>{selectedProduct.name}</h2>
                            <p>{selectedProduct.description || 'Produto disponivel para compra online.'}</p>
                            {(selectedProduct.options?.sizes?.length || selectedProduct.options?.colors?.length) && (
                                <div className="storefront-variant-picker">
                                    {selectedProduct.options?.sizes?.length > 0 && (
                                        <div>
                                            <span>Capacidade</span>
                                            <div className="storefront-variant-list">
                                                {(selectedProduct.options?.sizes || []).map((size) => (
                                                    <button className={selectedVariant.size === size ? 'active' : ''} type="button" key={`size-${size}`} onClick={() => selectVariant({ size })}>
                                                        {size}
                                                        <small>{money(productDisplayPrice(selectedProduct, size, selectedVariant.color))}</small>
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                    {selectedProduct.options?.colors?.length > 0 && (
                                        <div>
                                            <span>Cor</span>
                                            <div className="storefront-variant-list">
                                                {(selectedProduct.options?.colors || []).map((color) => (
                                                    <button className={selectedVariant.color === color ? 'active' : ''} type="button" key={`color-${color}`} onClick={() => selectVariant({ color })}>
                                                        {color}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}
                            <strong className="storefront-modal-price">{money(productDisplayPrice(selectedProduct, selectedVariant.size, selectedVariant.color))}</strong>
                            <div className="storefront-modal-actions">
                                <button className="storefront-details-button" type="button" onClick={() => addToCart(selectedProduct)}>Adicionar ao carrinho</button>
                                <a className="storefront-buy-button" href={`${selectedProduct.public_url}?${new URLSearchParams({ size: selectedVariant.size || '', color: selectedVariant.color || '' }).toString()}`} onClick={(event) => {
                                    if (!requireStoreLogin()) event.preventDefault();
                                }}>Comprar agora</a>
                            </div>
                        </div>
                    </section>
                </div>
            )}

            {cartOpen && (
                <aside className="storefront-cart-drawer">
                    <div className="storefront-cart-header">
                        <strong>Carrinho</strong>
                        <button className="icon-button" type="button" onClick={() => setCartOpen(false)}><X size={17} /></button>
                    </div>
                    <div className="storefront-cart-items">
                        {cart.map((item) => (
                            <article key={item.key || item.product.id}>
                                <div className="storefront-cart-item-main">
                                    <strong>{item.product.name}</strong>
                                    {(item.product.selected_size || item.product.selected_color) && <small>{[item.product.selected_size, item.product.selected_color].filter(Boolean).join(' / ')}</small>}
                                    <span>{money(item.product.final_amount_cents ?? item.product.price_cents)}</span>
                                </div>
                                <label className="storefront-cart-qty">
                                    <small>Qtd</small>
                                    <input type="number" min="1" max="99" value={item.quantity} onChange={(event) => updateCartQuantity(item.key || item.product.id, event.target.value)} />
                                </label>
                                <div className="storefront-cart-actions">
                                    <a href={`${item.product.public_url}?${new URLSearchParams({ qty: item.quantity, size: item.product.selected_size || '', color: item.product.selected_color || '' }).toString()}`} onClick={(event) => {
                                        if (!requireStoreLogin()) event.preventDefault();
                                    }}>Finalizar</a>
                                    <button type="button" onClick={() => removeFromCart(item.key || item.product.id)}>Remover</button>
                                </div>
                            </article>
                        ))}
                        {!cart.length && <div className="empty-state">Seu carrinho esta vazio.</div>}
                    </div>
                    <div className="storefront-cart-total">
                        <span>Total</span>
                        <strong>{money(cartTotal)}</strong>
                    </div>
                </aside>
            )}
        </main>
    );
}

function PublicCustomerAccountPage({ mode }) {
    const pathParts = window.location.pathname.split('/').filter(Boolean);
    const slug = pathParts[1] || '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const [tenant, setTenant] = useState(null);
    const [form, setForm] = useState({ name: '', email: '', password: '', phone: '', cpf: '' });
    const [notice, setNotice] = useState('');
    const [loading, setLoading] = useState(true);
    const isRegister = mode === 'register';

    useEffect(() => {
        fetch(`/loja/${slug}/data`, { headers: { Accept: 'application/json' } })
            .then((response) => {
                if (!response.ok) throw new Error('Loja indisponivel.');
                return response.json();
            })
            .then((data) => {
                setTenant(data.tenant);
                document.documentElement.style.setProperty('--checkout-primary-color', data.tenant.checkout_primary_color || '#3b82f6');
                document.documentElement.style.setProperty('--checkout-button-color', data.tenant.checkout_button_color || '#43c97b');
            })
            .catch(() => setNotice('Esta loja nao esta disponivel no momento.'))
            .finally(() => setLoading(false));
    }, [slug]);

    async function submitCustomerAccount(event) {
        event.preventDefault();
        setNotice('');
        const response = await fetch(isRegister ? '/customer/register' : '/customer/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ ...form, store_slug: slug }),
        });

        if (!response.ok) {
            setNotice(isRegister ? 'Nao foi possivel criar a conta. Confira os dados.' : 'E-mail ou senha invalidos para esta loja.');
            return;
        }

        const next = new URLSearchParams(window.location.search).get('next');
        window.location.href = next && next.startsWith('/') ? next : `/loja/${slug}`;
    }

    if (loading) {
        return (
            <main className="result-shell">
                <section className="result-panel pending">
                    <Loader label="Carregando loja..." />
                </section>
            </main>
        );
    }

    return (
        <main className="customer-auth-page">
            <section className="customer-auth-card">
                <a className="customer-auth-brand" href={`/loja/${slug}`}>
                    <span>{String(tenant?.name || 'L').slice(0, 1)}</span>
                    <div>
                        <strong>{tenant?.store_title || tenant?.name || 'Loja'}</strong>
                        <small>Conta de cliente</small>
                    </div>
                </a>
                <div>
                    <h1>{isRegister ? 'Criar conta' : 'Entrar na loja'}</h1>
                    <p>{isRegister ? 'Cadastre seus dados para comprar e salvar enderecos nesta empresa.' : 'Acesse sua conta de cliente desta empresa para continuar comprando.'}</p>
                </div>
                {notice && <div className="notice">{notice}</div>}
                <form className="customer-auth-form" onSubmit={submitCustomerAccount}>
                    {isRegister && (
                        <>
                            <Field label="Nome completo">
                                <input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} required />
                            </Field>
                            <Field label="Telefone">
                                <input value={form.phone} onChange={(event) => setForm({ ...form, phone: event.target.value })} />
                            </Field>
                            <Field label="CPF">
                                <input value={form.cpf} onChange={(event) => setForm({ ...form, cpf: event.target.value })} />
                            </Field>
                        </>
                    )}
                    <Field label="E-mail">
                        <input type="email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} required />
                    </Field>
                    <Field label="Senha">
                        <input type="password" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} required minLength="6" />
                    </Field>
                    <button className="primary-button">{isRegister ? 'Criar conta' : 'Entrar'}</button>
                    <a className="secondary-button customer-auth-switch" href={`/loja/${slug}/${isRegister ? 'entrar' : 'criar-conta'}${window.location.search}`}>
                        {isRegister ? 'Ja tenho conta' : 'Criar nova conta'}
                    </a>
                </form>
            </section>
        </main>
    );
}

function PublicSalesPage({ mode }) {
    const [link, setLink] = useState(null);
    const [tenant, setTenant] = useState({ name: 'store.checkout', payment_provider_label: 'Mercado Pago' });
    const [error, setError] = useState('');
    const [session, setSession] = useState({ checked: false, authenticated: false, user: null });
    const [checkoutForm, setCheckoutForm] = useState({ first_name: '', last_name: '', email: '', phone: '', cpf: '', cep: '', selected_size: '', selected_color: '' });
    const [checkoutAddressId, setCheckoutAddressId] = useState('');
    const [addressForm, setAddressForm] = useState({ label: 'Principal', cep: '', street: '', number: '', complement: '', neighborhood: '', city: '', state: '', default: true });
    const [pixPayment, setPixPayment] = useState(null);
    const [pixLoading, setPixLoading] = useState(false);
    const [pixError, setPixError] = useState('');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    useEffect(() => {
        Promise.all([
            fetch('/auth/session', { headers: { Accept: 'application/json' } }).then((response) => response.json()),
            fetch('/tenant-settings/public', { headers: { Accept: 'application/json' } }).then((response) => response.json()),
            fetch(`${window.location.pathname}/data`, { headers: { Accept: 'application/json' } }).then((response) => {
                if (!response.ok) {
                    throw new Error('Link indisponivel.');
                }

                return response.json();
            }),
        ])
            .then(([sessionData, tenantData, linkData]) => {
                const effectiveTenant = linkData.tenant || tenantData;
                const customer = sessionData.customer || null;
                const sameTenant = customer && Number(customer.tenant_setting_id) === Number(effectiveTenant.id);
                setSession({ checked: true, authenticated: Boolean(sessionData.authenticated && sameTenant), user: sameTenant ? customer : null });
                setTenant(effectiveTenant);
                document.documentElement.style.setProperty('--checkout-primary-color', effectiveTenant.checkout_primary_color || '#3b82f6');
                document.documentElement.style.setProperty('--checkout-button-color', effectiveTenant.checkout_button_color || '#43c97b');
                setLink(linkData);
                if (mode === 'product') {
                    const params = new URLSearchParams(window.location.search);
                    setCheckoutForm((current) => ({
                        ...current,
                        selected_size: params.get('size') || linkData.options?.variants?.[0]?.size || linkData.options?.sizes?.[0] || '',
                        selected_color: params.get('color') || linkData.options?.variants?.[0]?.color || linkData.options?.colors?.[0] || '',
                    }));
                }
            })
            .catch(() => setError('Este link de venda nao esta disponivel.'));
    }, []);

    useEffect(() => {
        if (!pixPayment || pixPayment.status === 'approved') {
            return undefined;
        }

        const timer = window.setInterval(() => {
            const statusUrl = mode === 'product' && pixPayment.sale_id
                ? `${window.location.pathname}/status?sale=${pixPayment.sale_id}`
                : `${window.location.pathname}/status`;

            fetch(statusUrl, { headers: { Accept: 'application/json' } })
                .then((response) => response.json())
                .then((data) => {
                    if (data.payment) {
                        setPixPayment((current) => ({ ...current, ...data.payment }));
                    }

                    if (data.sales_link_status) {
                        setLink((current) => current ? { ...current, status: data.sales_link_status } : current);
                    }
                });
        }, 6000);

        return () => window.clearInterval(timer);
    }, [pixPayment, mode]);

    useEffect(() => {
        if (pixPayment?.status === 'approved') {
            const sale = pixPayment.sale_id || new URLSearchParams(window.location.search).get('sale') || '';
            const timer = window.setTimeout(() => {
                window.location.href = `/checkout/resultado?status=success&link=${sale}`;
            }, 1800);

            return () => window.clearTimeout(timer);
        }

        return undefined;
    }, [pixPayment?.status, pixPayment?.sale_id]);

    async function generatePix(event) {
        event.preventDefault();
        setPixLoading(true);
        setPixError('');

        const response = await fetch(`${window.location.pathname}/pix`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                ...checkoutForm,
                name: `${checkoutForm.first_name} ${checkoutForm.last_name}`.trim(),
                shipping_region: shippingEstimate?.region || null,
                shipping_eta: shippingEstimate?.eta || null,
                shipping_amount_cents: shippingAmount,
                quantity: checkoutQuantity,
                customer_address_id: checkoutAddressId || null,
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            const validationMessage = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null;
            setPixError(validationMessage || data.message || 'Nao foi possivel gerar o Pix.');
            if (data.sale_id) {
                setPixPayment({
                    sale_id: data.sale_id,
                    status: data.status || 'pending',
                    qr_code: '',
                    qr_code_base64: '',
                });
                setLink((current) => current ? { ...current, status: data.status || 'pending' } : current);
            }
            setPixLoading(false);
            return;
        }

        setPixPayment(data);
        setPixLoading(false);
    }

    async function submitCheckoutAddress(event) {
        event.preventDefault();
        const response = await fetch('/customer/addresses', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ ...addressForm, state: addressForm.state.toUpperCase() }),
        });

        if (response.ok) {
            const address = await response.json();
            setSession((current) => ({
                ...current,
                user: {
                    ...current.user,
                    addresses: [...(current.user?.addresses || []), address],
                },
            }));
            setCheckoutAddressId(String(address.id));
            setCheckoutForm((current) => ({ ...current, cep: address.cep }));
            setAddressForm({ label: 'Principal', cep: '', street: '', number: '', complement: '', neighborhood: '', city: '', state: '', default: true });
        }
    }

    async function copyPixCode() {
        if (!pixPayment?.qr_code) {
            return;
        }

        await navigator.clipboard.writeText(pixPayment.qr_code);
    }

    if (error) {
        return (
            <main className="result-shell">
                <section className="result-panel failure">
                    <CircleAlert size={42} />
                    <p className="eyebrow">Store TI</p>
                    <h1>Link indisponivel</h1>
                    <p>{error}</p>
                </section>
            </main>
        );
    }

    if (!link) {
        return (
            <main className="result-shell">
                <section className="result-panel pending">
                    <Loader label="Carregando venda..." />
                </section>
            </main>
        );
    }

    if (session.checked && !session.authenticated) {
        const next = encodeURIComponent(window.location.pathname + window.location.search);
        const loginUrl = `/loja/${tenant.store_slug}/entrar?next=${next}`;
        const registerUrl = `/loja/${tenant.store_slug}/criar-conta?next=${next}`;

        return (
            <main className="result-shell">
                <section className="result-panel pending account-required-panel">
                    <CircleAlert size={42} />
                    <p className="eyebrow">{tenant.store_title || tenant.name || 'Loja'}</p>
                    <h1>Entre para comprar</h1>
                    <p>Use uma conta de cliente desta loja para finalizar o pedido.</p>
                    <div className="checkout-auth-actions">
                        <a className="primary-button account-login-button" href={loginUrl}>Entrar</a>
                        <a className="secondary-button account-login-button" href={registerUrl}>Criar conta</a>
                    </div>
                </section>
            </main>
        );
    }

    const product = mode === 'product' ? link : link.product;
    const checkoutVariant = mode === 'product' ? findProductVariant(product, checkoutForm.selected_size, checkoutForm.selected_color) : null;
    const checkoutUnitPrice = checkoutVariant?.price_cents ?? product?.final_amount_cents ?? product?.price_cents ?? 0;
    const title = mode === 'product' ? link.name : link.title;
    const description = product?.description || `Contratacao segura com pagamento processado via ${tenant.payment_provider_label || 'Mercado Pago'}.`;
    const checkoutQuantity = mode === 'product'
        ? Math.max(Number(new URLSearchParams(window.location.search).get('qty') || 1), 1)
        : Number(link.quantity || 1);
    const originalAmount = mode === 'product' ? checkoutUnitPrice * checkoutQuantity : link.original_amount_cents;
    const discountAmount = mode === 'product' && checkoutVariant ? 0 : mode === 'product' ? link.discount_amount_cents * checkoutQuantity : link.discount_amount_cents;
    const selectedAddress = (session.user?.addresses || []).find((address) => String(address.id) === String(checkoutAddressId));
    const shippingEstimate = (() => {
        if (!product?.requires_shipping) return null;

        const cep = (selectedAddress?.cep || checkoutForm.cep).replace(/\D/g, '');
        const regions = tenant.store_shipping_regions || [];
        const exact = regions.find((region) => region.cep_prefix && cep.startsWith(String(region.cep_prefix)));
        const fallback = regions.find((region) => !region.cep_prefix);

        return exact || fallback || null;
    })();
    const shippingAmount = shippingEstimate?.price_cents || 0;
    const finalAmount = (mode === 'product' ? checkoutUnitPrice * checkoutQuantity : link.final_amount_cents) + shippingAmount;
    const hasDiscount = discountAmount > 0;
    const paymentApproved = pixPayment?.status === 'approved';
    const paymentFailed = ['rejected', 'cancelled'].includes(pixPayment?.status);

    return (
        <main className="checkout-page">
            <header className="checkout-brand">
                <strong>{tenant.store_title || tenant.name || 'store.checkout'}</strong>
            </header>

            <section className="checkout-title">
                <div>
                    <h1>Finalizar Compra</h1>
                    <p>Preencha os campos abaixo e clique em Finalizar Pedido para concluir a compra.</p>
                </div>
                <span>Compra segura via {tenant.payment_provider_label || 'Mercado Pago'}</span>
            </section>

            <section className="checkout-left">
                {!pixPayment && (
                    <form className="pix-form" onSubmit={generatePix}>
                        <section className="checkout-box">
                            <div className="checkout-box-title">
                                <span>1</span>
                                <div>
                                    <h2>Informações Pessoais</h2>
                                    <p>Para quem é o pedido?</p>
                                </div>
                            </div>
                            <div className="checkout-fields">
                                <div className="checkout-customer-card">
                                    <strong>{session.user?.name}</strong>
                                    <span>{session.user?.email}</span>
                                    {session.user?.phone && <span>{session.user.phone}</span>}
                                </div>
                                {product?.requires_shipping && (
                                    <Field label="Endereco de entrega">
                                        <select value={checkoutAddressId} onChange={(event) => {
                                            const address = (session.user?.addresses || []).find((item) => String(item.id) === event.target.value);
                                            setCheckoutAddressId(event.target.value);
                                            setCheckoutForm({ ...checkoutForm, cep: address?.cep || checkoutForm.cep });
                                        }} required>
                                            <option value="">Selecione um endereco</option>
                                            {(session.user?.addresses || []).map((address) => (
                                                <option value={address.id} key={address.id}>{address.label} - {address.street}, {address.number} - {address.city}/{address.state}</option>
                                            ))}
                                        </select>
                                    </Field>
                                )}
                                {product?.requires_shipping && !(session.user?.addresses || []).length && (
                                    <div className="checkout-address-create">
                                        <strong>Cadastre um endereco de entrega</strong>
                                        <div className="checkout-fields">
                                            <Field label="Apelido"><input value={addressForm.label} onChange={(event) => setAddressForm({ ...addressForm, label: event.target.value })} /></Field>
                                            <Field label="CEP"><input value={addressForm.cep} onChange={(event) => setAddressForm({ ...addressForm, cep: event.target.value })} required /></Field>
                                            <Field label="Rua"><input value={addressForm.street} onChange={(event) => setAddressForm({ ...addressForm, street: event.target.value })} required /></Field>
                                            <Field label="Numero"><input value={addressForm.number} onChange={(event) => setAddressForm({ ...addressForm, number: event.target.value })} required /></Field>
                                            <Field label="Bairro"><input value={addressForm.neighborhood} onChange={(event) => setAddressForm({ ...addressForm, neighborhood: event.target.value })} required /></Field>
                                            <Field label="Cidade"><input value={addressForm.city} onChange={(event) => setAddressForm({ ...addressForm, city: event.target.value })} required /></Field>
                                            <Field label="UF"><input maxLength="2" value={addressForm.state} onChange={(event) => setAddressForm({ ...addressForm, state: event.target.value.toUpperCase() })} required /></Field>
                                        </div>
                                        <button className="secondary-button" type="button" onClick={submitCheckoutAddress}>Salvar endereco</button>
                                    </div>
                                )}
                                <Field label="Nome">
                                    <input value={checkoutForm.first_name} onChange={(event) => setCheckoutForm({ ...checkoutForm, first_name: event.target.value })} placeholder="Opcional" />
                                </Field>
                                <Field label="Sobrenome">
                                    <input value={checkoutForm.last_name} onChange={(event) => setCheckoutForm({ ...checkoutForm, last_name: event.target.value })} placeholder="Opcional" />
                                </Field>
                                <Field label="E-mail">
                                    <input type="email" value={checkoutForm.email} onChange={(event) => setCheckoutForm({ ...checkoutForm, email: event.target.value })} placeholder="Opcional" />
                                </Field>
                                <Field label="CPF">
                                    <input value={checkoutForm.cpf} onChange={(event) => setCheckoutForm({ ...checkoutForm, cpf: event.target.value })} placeholder="Opcional" />
                                </Field>
                                <Field label="Celular">
                                    <input value={checkoutForm.phone} onChange={(event) => setCheckoutForm({ ...checkoutForm, phone: event.target.value })} placeholder="Opcional" />
                                </Field>
                                {product?.requires_shipping && (
                                    <Field label="CEP">
                                        <input value={checkoutForm.cep} onChange={(event) => setCheckoutForm({ ...checkoutForm, cep: event.target.value })} placeholder="CEP" />
                                    </Field>
                                )}
                                {product?.options?.sizes?.length > 0 && (
                                    <div className="checkout-variant-picker">
                                        <strong>Capacidade</strong>
                                        <div className="storefront-variant-list">
                                            {product.options.sizes.map((size) => (
                                                <button className={checkoutForm.selected_size === size ? 'active' : ''} type="button" key={size} onClick={() => setCheckoutForm({ ...checkoutForm, selected_size: size })}>
                                                    {size}
                                                    <small>{money(productDisplayPrice(product, size, checkoutForm.selected_color))}</small>
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                )}
                                {product?.options?.colors?.length > 0 && (
                                    <div className="checkout-variant-picker">
                                        <strong>Cor</strong>
                                        <div className="storefront-variant-list">
                                            {product.options.colors.map((color) => (
                                                <button className={checkoutForm.selected_color === color ? 'active' : ''} type="button" key={color} onClick={() => setCheckoutForm({ ...checkoutForm, selected_color: color })}>
                                                    {color}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </section>

                        <section className="checkout-box">
                            <div className="checkout-box-title">
                                <span>2</span>
                                <div>
                                    <h2>Método de Envio</h2>
                                    <p>Como deseja receber o seu pedido?</p>
                                </div>
                            </div>
                            <div className="shipping-option">
                                <strong>{product?.requires_shipping ? (shippingEstimate?.region || 'Informe o CEP') : 'Entrega digital / ativacao'}</strong>
                                <span>{product?.requires_shipping ? money(shippingAmount) : 'Gratis'}</span>
                            </div>
                            {product?.requires_shipping && shippingEstimate?.eta && <p className="helper-text">Prazo estimado: {shippingEstimate.eta}</p>}
                        </section>

                        <section className="checkout-box">
                            <div className="checkout-box-title">
                                <span>3</span>
                                <div>
                                    <h2>Método de Pagamento</h2>
                                    <p>Como deseja pagar o seu pedido?</p>
                                </div>
                            </div>
                            <div className="payment-method-option">
                                <span className="radio-dot" />
                                <strong>Pix</strong>
                                <em>{tenant.payment_provider_label || 'Mercado Pago'}</em>
                            </div>
                        </section>
                    </form>
                )}
                {!link.payment_configured && (
                    <div className="warning-box">Pagamento ainda nao configurado pelo vendedor.</div>
                )}
                {pixError && <div className="warning-box">{pixError}</div>}
                {pixPayment && (
                    <section className={`pix-payment-box ${paymentApproved ? 'approved' : ''}`}>
                        {paymentApproved ? (
                            <div className="approval-box">
                                <CheckCircle2 size={44} />
                                <h2>Pagamento aprovado</h2>
                                <p>Recebemos a confirmacao do Mercado Pago. Sua contratacao foi registrada.</p>
                            </div>
                        ) : !pixPayment.qr_code ? (
                            <div className="approval-box">
                                <CircleAlert size={44} />
                                <h2>Venda registrada</h2>
                                <p>Nao conseguimos gerar o Pix agora. A equipe pode acompanhar esta venda pelo painel e tentar novamente.</p>
                            </div>
                        ) : (
                            <>
                                <div className="pix-grid">
                                    {pixPayment.qr_code_base64 && (
                                        <img src={`data:image/png;base64,${pixPayment.qr_code_base64}`} alt="QR Code Pix" />
                                    )}
                                    <div className="pix-instructions">
                                        <h3>Finalize pelo Pix</h3>
                                        <p>Escaneie o QR Code ou copie o codigo. Esta tela atualiza automaticamente quando o Mercado Pago confirmar.</p>
                                        <button className="secondary-button" onClick={copyPixCode}>Copiar codigo Pix</button>
                                        {pixPayment.ticket_url && (
                                            <a className="secondary-link" href={pixPayment.ticket_url} target="_blank" rel="noreferrer">Abrir no Mercado Pago</a>
                                        )}
                                    </div>
                                </div>
                                <textarea readOnly value={pixPayment.qr_code || ''} rows="5" />
                            </>
                        )}
                    </section>
                )}
            </section>

            <aside className="checkout-summary">
                <div className="checkout-box-title">
                    <span>✓</span>
                    <div>
                        <h2>Confirmação do Pedido</h2>
                        <p>Confira as informações do seu pedido</p>
                    </div>
                </div>
                <label className="checkout-checkbox">
                    <input type="checkbox" disabled />
                    Finalizar compra como pessoa jurídica?
                </label>
                <div className="order-table">
                    <div className="order-table-head">
                        <span>Nome do Produto</span>
                        <span>Preço</span>
                        <span>Qtd</span>
                        <span>Subtotal</span>
                    </div>
                    <div className="order-item">
                        <div>
                            <strong>{product?.name || title}</strong>
                            <small>{description}</small>
                            {(checkoutForm.selected_size || checkoutForm.selected_color) && (
                                <small>{[checkoutForm.selected_size, checkoutForm.selected_color].filter(Boolean).join(' / ')}</small>
                            )}
                        </div>
                        <span>{money(originalAmount)}</span>
                        <span>{checkoutQuantity}</span>
                        <strong>{money(finalAmount - shippingAmount)}</strong>
                    </div>
                </div>
                <div className="order-totals">
                    <div><span>Subtotal</span><strong>{money(originalAmount)}</strong></div>
                    <div><span>Desconto</span><strong>{money(discountAmount)}</strong></div>
                    <div><span>Entrega</span><strong>{product?.requires_shipping ? money(shippingAmount) : money(0)}</strong></div>
                    <div className="order-total"><span>Valor Total</span><strong>{money(finalAmount)}</strong></div>
                </div>
                <button className="checkout-button" disabled={Boolean(pixPayment) || !link.payment_configured || pixLoading} onClick={() => document.querySelector('.pix-form')?.requestSubmit()}>
                    {pixLoading ? 'GERANDO PIX...' : 'FINALIZAR PEDIDO'}
                </button>
                <small className="secure-note">Ambiente de compra seguro com processamento Mercado Pago.</small>
            </aside>
        </main>
    );
}

function CheckoutResult() {
    const params = new URLSearchParams(window.location.search);
    const status = params.get('status') || 'pending';

    const content = {
        success: {
            icon: CheckCircle2,
            title: 'Pagamento recebido',
            message: 'Seu pedido foi registrado. A confirmacao final pode levar alguns instantes.',
            tone: 'success',
        },
        failure: {
            icon: CircleAlert,
            title: 'Pagamento nao concluido',
            message: 'A transacao nao foi aprovada. Voce pode tentar novamente pelo link de venda.',
            tone: 'failure',
        },
        pending: {
            icon: Activity,
            title: 'Pagamento em analise',
            message: 'Recebemos o retorno do checkout e estamos aguardando a confirmacao do Mercado Pago.',
            tone: 'pending',
        },
    }[status] || {
        icon: Activity,
        title: 'Status em processamento',
        message: 'O pagamento esta sendo atualizado. Aguarde a confirmacao do vendedor.',
        tone: 'pending',
    };

    const Icon = content.icon;

    return (
        <main className="result-shell">
            <section className={`result-panel ${content.tone}`}>
                <Icon size={42} />
                <p className="eyebrow">Store TI</p>
                <h1>{content.title}</h1>
                <p>{content.message}</p>
            </section>
        </main>
    );
}

function ReportsPanel({ reports, reportPeriod, setReportPeriod, applyReportPeriod, setQuickReportPeriod }) {
    if (!reports) {
        return (
            <section className="panel">
                <div className="panel-title">
                    <Activity size={20} />
                    <h2>Carregando relatorios</h2>
                </div>
                <p className="helper-text">Buscando indicadores comerciais do periodo.</p>
            </section>
        );
    }

    const maxDailyRevenue = Math.max(...reports.daily_revenue.map((day) => day.revenue_cents), 1);
    const maxStatusCount = Math.max(...reports.status_breakdown.map((item) => item.count), 1);
    const maxProductRevenue = Math.max(...reports.top_products.map((product) => product.revenue_cents), 1);

    return (
        <section className="reports-layout">
            <form className="report-toolbar" onSubmit={applyReportPeriod}>
                <div>
                    <h2>Relatorio comercial</h2>
                    <p>{formatShortDate(reports.period.from)} ate {formatShortDate(reports.period.to)}</p>
                </div>
                <div className="report-period-controls">
                    <button className="secondary-button" type="button" onClick={() => setQuickReportPeriod(7)}>7 dias</button>
                    <button className="secondary-button" type="button" onClick={() => setQuickReportPeriod(30)}>30 dias</button>
                    <button className="secondary-button" type="button" onClick={() => setQuickReportPeriod(90)}>90 dias</button>
                    <input type="date" value={reportPeriod.from} onChange={(event) => setReportPeriod({ ...reportPeriod, from: event.target.value })} />
                    <input type="date" value={reportPeriod.to} onChange={(event) => setReportPeriod({ ...reportPeriod, to: event.target.value })} />
                    <button className="primary-button compact-primary" type="submit">Aplicar</button>
                </div>
            </form>

            <section className="report-kpis">
                <Metric icon={CreditCard} label="Receita aprovada" value={money(reports.summary.revenue_cents)} />
                <Metric icon={ShoppingCart} label="Vendas no periodo" value={reports.summary.sales_total} />
                <Metric icon={CheckCircle2} label="Pagas" value={reports.summary.paid_sales} />
                <Metric icon={BadgePercent} label="Conversao" value={`${reports.summary.conversion_rate}%`} />
                <Metric icon={Activity} label="Ticket medio" value={money(reports.summary.average_ticket_cents)} />
                <Metric icon={BadgePercent} label="Descontos" value={money(reports.summary.discount_cents)} />
            </section>

            <section className="report-grid">
                <article className="panel report-chart-panel">
                    <div className="panel-title">
                        <BarChart3 size={20} />
                        <h2>Receita diaria</h2>
                    </div>
                    <div className="daily-bars">
                        {reports.daily_revenue.map((day) => (
                            <div className="daily-bar" key={day.date} title={`${formatShortDate(day.date)} - ${money(day.revenue_cents)}`}>
                                <span style={{ height: `${Math.max((day.revenue_cents / maxDailyRevenue) * 100, day.revenue_cents > 0 ? 8 : 2)}%` }} />
                                <small>{formatShortDate(day.date)}</small>
                            </div>
                        ))}
                    </div>
                </article>

                <article className="panel">
                    <div className="panel-title">
                        <Activity size={20} />
                        <h2>Status das vendas</h2>
                    </div>
                    <div className="report-bars">
                        {reports.status_breakdown.map((item) => (
                            <div className="report-bar-row" key={item.status}>
                                <div>
                                    <strong>{item.label}</strong>
                                    <span>{item.count} vendas - {money(item.amount_cents)}</span>
                                </div>
                                <div className="report-progress">
                                    <span style={{ width: `${(item.count / maxStatusCount) * 100}%` }} />
                                </div>
                            </div>
                        ))}
                    </div>
                </article>
            </section>

            <section className="report-grid">
                <article className="panel">
                    <div className="panel-title">
                        <Box size={20} />
                        <h2>Produtos com melhor desempenho</h2>
                    </div>
                    <div className="product-ranking">
                        {reports.top_products.map((product, index) => (
                            <div className="ranking-row" key={product.product_id || product.name}>
                                <span>{index + 1}</span>
                                <div>
                                    <strong>{product.name}</strong>
                                    <small>{product.sales_count} vendas - {product.paid_count} pagas</small>
                                    <div className="report-progress">
                                        <span style={{ width: `${(product.revenue_cents / maxProductRevenue) * 100}%` }} />
                                    </div>
                                </div>
                                <strong>{money(product.revenue_cents)}</strong>
                            </div>
                        ))}
                        {!reports.top_products.length && <div className="empty-state">Sem vendas no periodo selecionado.</div>}
                    </div>
                </article>

                <article className="panel">
                    <div className="panel-title">
                        <CreditCard size={20} />
                        <h2>Ultimos pagamentos aprovados</h2>
                    </div>
                    <div className="payment-feed">
                        {reports.recent_payments.map((payment) => (
                            <div className="payment-feed-row" key={payment.id}>
                                <div>
                                    <strong>{payment.customer.name || 'Cliente nao informado'}</strong>
                                    <span>{payment.product || 'Produto nao informado'}</span>
                                    <small>{formatDate(payment.paid_at)} - {formatCpf(payment.customer.cpf)}</small>
                                </div>
                                <strong>{money(payment.amount_cents)}</strong>
                            </div>
                        ))}
                        {!reports.recent_payments.length && <div className="empty-state">Nenhum pagamento aprovado no periodo.</div>}
                    </div>
                </article>
            </section>

            <section className="system-health">
                <HealthCard title="Catalogo ativo" value={reports.catalog.active_products} description={`${reports.catalog.products_total} produtos cadastrados no total.`} tone="ready" />
                <HealthCard title="Planos de internet" value={reports.catalog.internet_plans} description="Ofertas de internet cadastradas no catalogo." tone="neutral" />
                <HealthCard title="Sem estoque" value={reports.catalog.without_stock_control} description="Produtos/servicos que nao bloqueiam venda por estoque." tone="draft" />
            </section>
        </section>
    );
}

function SuperAdminDashboard({ stats, operateCompany, editCompany }) {
    const companies = stats.companies || [];
    const activeCompany = stats.active_company;

    return (
        <section className="superadmin-dashboard">
            <section className="metrics">
                <Metric icon={Box} label="Empresas/clientes" value={stats.companies_total ?? 0} />
                <Metric icon={Users} label="Usuarios ativos" value={stats.active_users ?? 0} />
                <Metric icon={ShoppingCart} label="Vendas na plataforma" value={stats.platform_sales ?? 0} />
                <Metric icon={CreditCard} label="Receita plataforma" value={money(stats.platform_revenue_cents ?? 0)} />
            </section>

            <section className="system-health">
                <HealthCard
                    title="Empresa em visualizacao"
                    value={activeCompany?.name || 'Nenhuma'}
                    description={activeCompany ? `Visualizacao do superadmin. Gateway: ${activeCompany.active_payment_provider_label}` : 'Escolha uma empresa para visualizar o contexto.'}
                    tone={activeCompany ? 'ready' : 'draft'}
                />
                <HealthCard
                    title="Atendentes/Admins"
                    value={(stats.admins ?? 0) + (stats.sellers ?? 0)}
                    description={`${stats.admins ?? 0} admins, ${stats.sellers ?? 0} vendedores, ${stats.superadmins ?? 0} superadmins.`}
                    tone="neutral"
                />
                <HealthCard
                    title="Notificacoes"
                    value={stats.notifications_configured ? 'Ativo' : 'Pendente'}
                    description={stats.notifications_configured ? 'Evolution configurado para avisos operacionais.' : 'Configure Evolution para alertas por WhatsApp.'}
                    tone={stats.notifications_configured ? 'ready' : 'draft'}
                />
            </section>

            <section className="panel">
                <div className="panel-title">
                    <Box size={20} />
                    <h2>Clientes / empresas</h2>
                    <span>{companies.length} registros</span>
                </div>
                <div className="super-company-grid">
                    {companies.map((company) => (
                        <article className={`super-company-card ${company.is_current ? 'active' : ''}`} key={company.id}>
                            <div className="company-card-head">
                                <div>
                                    <span className="row-kicker">{company.is_current ? 'Visualizando agora' : 'Cliente'}</span>
                                    <strong>{company.name}</strong>
                                </div>
                                <span className={`status ${company.is_current ? 'ready' : 'draft'}`}>{company.is_current ? 'Em contexto' : 'Empresa'}</span>
                            </div>
                            <div className="company-card-meta">
                                <span>{company.support_email || 'Sem e-mail'}</span>
                                <span>{company.support_phone || 'Sem telefone'}</span>
                                <span>{company.active_payment_provider_label}</span>
                                <span>{company.enabled_providers} gateways ativos</span>
                                <span>{company.admins_count} admins</span>
                                <span>{company.sellers_count} vendedores</span>
                                <span>{company.active_users_count} usuarios ativos</span>
                            </div>
                            <div className="company-colors">
                                <i style={{ background: company.admin_primary_color }} />
                                <i style={{ background: company.checkout_primary_color }} />
                            </div>
                            <div className="row-actions">
                                {!company.is_current && (
                                    <button className="secondary-button" type="button" onClick={() => operateCompany(company)}>Visualizar</button>
                                )}
                                <button className="icon-button" type="button" title="Editar empresa" onClick={() => editCompany(company)}>
                                    <Pencil size={17} />
                                </button>
                            </div>
                        </article>
                    ))}
                    {!companies.length && <div className="empty-state">Nenhuma empresa cadastrada.</div>}
                </div>
            </section>
        </section>
    );
}

function StatusTile({ label, value, tone }) {
    return (
        <div className={`status-tile ${tone}`}>
            <span>{label}</span>
            <strong>{value}</strong>
        </div>
    );
}

function HealthCard({ title, description, value, tone }) {
    return (
        <article className={`health-card ${tone}`}>
            <div>
                <span>{title}</span>
                <strong>{value}</strong>
            </div>
            <p>{description}</p>
        </article>
    );
}

function SalesList({ title = 'Acompanhamento de vendas', links, copyUrl, refreshLink, updateSaleStatus, updateSaleDelivery, deleteSale }) {
    return (
        <section className="list-section">
            <div className="section-heading">
                <h2>{title}</h2>
                <span>{links.length} registros</span>
            </div>
            <div className="sales-table">
                {links.map((link) => {
                    const lastPayment = link.payments?.[0];
                    const customer = link.customer || {};
                    const effectiveStatus = lastPayment?.status === 'approved'
                        ? 'paid'
                        : ['pending', 'in_process'].includes(lastPayment?.status)
                            ? 'pending'
                            : ['rejected', 'cancelled', 'refunded', 'charged_back'].includes(lastPayment?.status)
                                ? 'cancelled'
                                : link.status;

                    return (
                        <article className="sale-row" key={link.id}>
                            <div className="sale-main">
                                <span className="row-kicker">Venda</span>
                                <strong>{link.title}</strong>
                                <p>{link.product?.name}</p>
                                <div className="customer-line">
                                    <span>{customer.name || 'Cliente nao informado'}</span>
                                    <span>{customer.email || 'E-mail nao informado'}</span>
                                    <span>{customer.phone || 'Contato nao informado'}</span>
                                    <span>{formatCpf(customer.cpf)}</span>
                                </div>
                                <small>Criada em {formatDate(link.created_at)}</small>
                            </div>
                            <div className="sale-meta">
                                <span className="row-kicker">Status</span>
                                <span className={`status ${effectiveStatus}`}>{statusLabel[effectiveStatus] || effectiveStatus}</span>
                                <small>{lastPayment ? `Pagamento: ${paymentStatusLabel[lastPayment.status] || lastPayment.status}` : 'Sem pagamento registrado'}</small>
                                <strong>{money(link.final_amount_cents)}</strong>
                                {link.discount_amount_cents > 0 && <small>Desconto: {money(link.discount_amount_cents)}</small>}
                                <select className="status-select" value={link.status} onChange={(event) => updateSaleStatus(link.public_id, event.target.value)}>
                                    <option value="draft">Configurar MP</option>
                                    <option value="ready">Pronto</option>
                                    <option value="pending">Em andamento</option>
                                    <option value="paid">Pago</option>
                                    <option value="cancelled">Cancelado</option>
                                </select>
                                {link.product?.requires_shipping && (
                                    <div className="delivery-admin-controls">
                                        <select className="status-select" defaultValue={link.delivery?.status || 'waiting_payment'} onChange={(event) => updateSaleDelivery(link.public_id, { delivery_status: event.target.value })}>
                                            <option value="waiting_payment">Aguardando pagamento</option>
                                            <option value="preparing">Preparando envio</option>
                                            <option value="shipped">Enviado</option>
                                            <option value="delivered">Entregue</option>
                                            <option value="cancelled">Entrega cancelada</option>
                                        </select>
                                        <input defaultValue={link.delivery?.tracking_code || ''} onBlur={(event) => updateSaleDelivery(link.public_id, { tracking_code: event.target.value })} placeholder="Codigo de rastreio" />
                                        <input defaultValue={link.delivery?.tracking_url || ''} onBlur={(event) => updateSaleDelivery(link.public_id, { tracking_url: event.target.value })} placeholder="URL de rastreio" />
                                    </div>
                                )}
                            </div>
                            <div className="row-actions">
                                <span className="row-kicker">Acoes</span>
                                <button className="icon-button" title="Copiar link de venda" onClick={() => copyUrl(link.public_url)}>
                                    <Copy size={17} />
                                </button>
                                <button className="icon-button" title="Sincronizar Mercado Pago" onClick={() => refreshLink(link.public_id)}>
                                    <RefreshCcw size={17} />
                                </button>
                                <a className="icon-link" href={link.public_url} target="_blank" rel="noreferrer" title="Abrir pagina de venda">
                                    <ExternalLink size={17} />
                                </a>
                                <button className="icon-button danger-action" title="Excluir venda" onClick={() => deleteSale(link.public_id)}>
                                    <Trash2 size={17} />
                                </button>
                            </div>
                        </article>
                    );
                })}
                {!links.length && <div className="empty-state">Cadastre um produto e gere o primeiro link de venda.</div>}
            </div>
        </section>
    );
}

function Metric({ icon: Icon, label, value }) {
    return (
        <div className="metric">
            <Icon size={20} />
            <span>{label}</span>
            <strong>{value}</strong>
        </div>
    );
}

createRoot(document.getElementById('root')).render(<App />);
