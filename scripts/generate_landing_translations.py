import json
import os

# Define complete translations for all landing phrases across 13 languages
translations = {
    "Features": {
        "hi": "सुविधाएँ",
        "ar": "المميزات",
        "es": "Características",
        "fr": "Fonctionnalités",
        "pt": "Recursos",
        "de": "Funktionen",
        "zh": "功能特点",
        "ja": "機能一覧",
        "ru": "Возможности",
        "it": "Funzionalità",
        "id": "Fitur",
        "tr": "Özellikler"
    },
    "Solutions": {
        "hi": "समाधान",
        "ar": "الحلول",
        "es": "Soluciones",
        "fr": "Solutions",
        "pt": "Soluções",
        "de": "Lösungen",
        "zh": "解决方案",
        "ja": "ソリューション",
        "ru": "Решения",
        "it": "Soluzioni",
        "id": "Solusi",
        "tr": "Çözümler"
    },
    "Hardware": {
        "hi": "हार्डवेयर",
        "ar": "الأجهزة",
        "es": "Hardware",
        "fr": "Matériel",
        "pt": "Hardware",
        "de": "Hardware",
        "zh": "硬件设备",
        "ja": "ハードウェア",
        "ru": "Оборудование",
        "it": "Hardware",
        "id": "Perangkat Keras",
        "tr": "Donanım"
    },
    "Pricing": {
        "hi": "मूल्य निर्धारण",
        "ar": "التسعير",
        "es": "Precios",
        "fr": "Tarifs",
        "pt": "Preços",
        "de": "Preise",
        "zh": "价格方案",
        "ja": "料金プラン",
        "ru": "Тарифы",
        "it": "Prezzi",
        "id": "Harga",
        "tr": "Fiyatlandırma"
    },
    "FAQs": {
        "hi": "अक्सर पूछे जाने वाले प्रश्न",
        "ar": "الأسئلة الشائعة",
        "es": "Preguntas Frecuentes",
        "fr": "FAQ",
        "pt": "Perguntas Frequentes",
        "de": "Häufige Fragen",
        "zh": "常见问题",
        "ja": "よくある質問",
        "ru": "Частые вопросы",
        "it": "Domande Frequenti",
        "id": "Tanya Jawab",
        "tr": "Sıkça Sorulan Sorular"
    },
    "Contact": {
        "hi": "संपर्क",
        "ar": "التواصل",
        "es": "Contacto",
        "fr": "Contact",
        "pt": "Contato",
        "de": "Kontakt",
        "zh": "联系我们",
        "ja": "お問い合わせ",
        "ru": "Контакты",
        "it": "Contatti",
        "id": "Kontak",
        "tr": "İletişim"
    },
    "Contact Us": {
        "hi": "हमसे संपर्क करें",
        "ar": "اتصل بنا",
        "es": "Contáctenos",
        "fr": "Contactez-nous",
        "pt": "Fale Conosco",
        "de": "Kontaktieren Sie uns",
        "zh": "联系我们",
        "ja": "お問い合わせ",
        "ru": "Свяжитесь с нами",
        "it": "Contattaci",
        "id": "Hubungi Kami",
        "tr": "Bize Ulaşın"
    },
    "Sign In": {
        "hi": "साइन इन",
        "ar": "تسجيل الدخول",
        "es": "Iniciar Sesión",
        "fr": "Se connecter",
        "pt": "Entrar",
        "de": "Anmelden",
        "zh": "登录",
        "ja": "ログイン",
        "ru": "Войти",
        "it": "Accedi",
        "id": "Masuk",
        "tr": "Giriş Yap"
    },
    "Sign In / POS": {
        "hi": "साइन इन / पीओएस",
        "ar": "تسجيل الدخول / نقطة البيع",
        "es": "Iniciar Sesión / TPV",
        "fr": "Connexion / Caisse",
        "pt": "Entrar / PDV",
        "de": "Anmelden / Kasse",
        "zh": "登录 / 收银POS",
        "ja": "ログイン / POSレジ",
        "ru": "Вход / POS-терминал",
        "it": "Accedi / Punto Cassa",
        "id": "Masuk / Mesin Kasir",
        "tr": "Giriş / POS Satış"
    },
    "Sign In to POS": {
        "hi": "पीओएस में साइन इन करें",
        "ar": "تسجيل الدخول إلى نقطة البيع",
        "es": "Acceder al TPV",
        "fr": "Accéder à la caisse",
        "pt": "Acessar o PDV",
        "de": "Zum POS-Kassensystem",
        "zh": "进入收银终端",
        "ja": "POSレジにログイン",
        "ru": "Войти в POS-систему",
        "it": "Accedi al POS",
        "id": "Masuk ke Sistem Kasir",
        "tr": "POS Ekranına Giriş Yap"
    },
    "Get Started": {
        "hi": "शुरू करें",
        "ar": "ابدأ الآن",
        "es": "Comenzar",
        "fr": "Commencer",
        "pt": "Começar",
        "de": "Jetzt starten",
        "zh": "立即开始",
        "ja": "今すぐ始める",
        "ru": "Начать",
        "it": "Inizia Ora",
        "id": "Mulai Sekarang",
        "tr": "Hemen Başla"
    },
    "Dashboard": {
        "hi": "डैशबोर्ड",
        "ar": "لوحة التحكم",
        "es": "Panel de Control",
        "fr": "Tableau de bord",
        "pt": "Painel de Controle",
        "de": "Dashboard",
        "zh": "仪表盘",
        "ja": "ダッシュボード",
        "ru": "Панель управления",
        "it": "Cruscotto",
        "id": "Dasbor",
        "tr": "Kontrol Paneli"
    },
    "Language": {
        "hi": "भाषा",
        "ar": "اللغة",
        "es": "Idioma",
        "fr": "Langue",
        "pt": "Idioma",
        "de": "Sprache",
        "zh": "语言",
        "ja": "言語",
        "ru": "Язык",
        "it": "Lingua",
        "id": "Bahasa",
        "tr": "Dil"
    },
    "Light Mode": {
        "hi": "लाइट मोड",
        "ar": "الوضع الفاتح",
        "es": "Modo Claro",
        "fr": "Mode Clair",
        "pt": "Modo Claro",
        "de": "Heller Modus",
        "zh": "明亮模式",
        "ja": "ライトモード",
        "ru": "Светлая тема",
        "it": "Modalità Chiara",
        "id": "Mode Terang",
        "tr": "Açık Tema"
    },
    "Dark Mode": {
        "hi": "डार्क मोड",
        "ar": "الوضع الداكن",
        "es": "Modo Oscuro",
        "fr": "Mode Sombre",
        "pt": "Modo Escuro",
        "de": "Dunkler Modus",
        "zh": "深色模式",
        "ja": "ダークモード",
        "ru": "Тёмная тема",
        "it": "Modalità Scura",
        "id": "Mode Gelap",
        "tr": "Koyu Tema"
    },
    "Smart POS • Inventory • Sales • Reports": {
        "hi": "स्मार्ट पीओएस • इन्वेंट्री • बिक्री • रिपोर्ट्स",
        "ar": "نقطة بيع ذكية • مخزون • مبيعات • تقارير",
        "es": "TPV Inteligente • Inventario • Ventas • Informes",
        "fr": "Caisse Intelligente • Stocks • Ventes • Rapports",
        "pt": "PDV Inteligente • Estoque • Vendas • Relatórios",
        "de": "Smart POS • Inventar • Verkauf • Berichte",
        "zh": "智能收银 • 实时库存 • 销售管理 • 深度报表",
        "ja": "スマートPOS • 在庫管理 • 販売管理 • 売上レポート",
        "ru": "Умная касса • Склад • Продажи • Отчёты",
        "it": "POS Intelligente • Magazzino • Vendite • Report",
        "id": "POS Pintar • Inventaris • Penjualan • Laporan",
        "tr": "Akıllı POS • Envanter • Satış • Raporlar"
    },
    "Everything You Need to Run Your Business": {
        "hi": "व्यवसाय चलाने के लिए आवश्यक सभी चीज़ें",
        "ar": "كل ما تحتاجه لإدارة وتنمية أعمالك التجارية",
        "es": "Todo lo que necesitas para gestionar tu negocio",
        "fr": "Tout ce dont vous avez besoin pour gérer votre entreprise",
        "pt": "Tudo o que você precisa para gerenciar seus negócios",
        "de": "Alles, was Sie zur Führung Ihres Unternehmens benötigen",
        "zh": "助您高效经营商业的一切全能工具",
        "ja": "ビジネス運営に必要なすべてがここに",
        "ru": "Всё необходимое для успешного ведения бизнеса",
        "it": "Tutto il necessario per gestire la tua attività",
        "id": "Segala yang Anda Butuhkan untuk Menjalankan Bisnis",
        "tr": "İşletmenizi Yönetmek İçin İhtiyacınız Olan Her Şey"
    },
    "Manage sales, inventory, customers, invoices and more — all in one powerful and easy-to-use platform. Perfect for retail stores, restaurants and growing businesses.": {
        "hi": "बिक्री, इन्वेंट्री, ग्राहक, चालान और बहुत कुछ प्रबंधित करें — सब एक शक्तिशाली और उपयोग में आसान प्लेटफ़ॉर्म पर। रिटेल स्टोर्स, रेस्तरां और बढ़ते व्यवसायों के लिए बिल्कुल उपयुक्त।",
        "ar": "إدارة المبيعات والمخزون والعملاء والفواتير وأكثر من ذلك — كل ذلك في منصة واحدة قوية وسهلة الاستخدام. مثالي لمحلات التجزئة والمطاعم والشركات الناشئة.",
        "es": "Gestiona ventas, inventario, clientes, facturas y más, todo en una plataforma potente y fácil de usar. Ideal para comercios minoristas, restaurantes y empresas en crecimiento.",
        "fr": "Gérez les ventes, les stocks, les clients, la facturation et plus encore dans une plateforme puissante et intuitive. Idéal pour le commerce de détail et la restauration.",
        "pt": "Gerencie vendas, estoque, clientes, faturas e muito mais — tudo em uma plataforma avançada e fácil de usar. Perfeito para lojas de varejo e restaurantes.",
        "de": "Verwalten Sie Verkäufe, Lagerbestände, Kunden, Rechnungen und mehr auf einer einzigen Plattform. Perfekt für Einzelhandel, Gastronomie und wachsende Unternehmen.",
        "zh": "在一个强大易用的平台上轻松管理销售、库存、会员、发票等多项业务。适用于零售超市、连锁餐饮及成长型企业。",
        "ja": "売上、在庫、顧客、請求書などを直感的な単一プラットフォームで一元管理。小売店、飲食店、成長企業に最適です。",
        "ru": "Управляйте продажами, складом, клиентами, счетами и аналитикой в одной удобной и мощной системе. Идеально для магазинов, кафе и растущего бизнеса.",
        "it": "Gestisci vendite, magazzino, clienti, fatture e molto altro in una piattaforma potente e intuitiva. Perfetto per negozi al dettaglio e ristorazione.",
        "id": "Kelola penjualan, stok barang, pelanggan, faktur, dan lainnya dalam satu sistem handal. Sempurna untuk toko ritel, restoran, dan UMKM.",
        "tr": "Satışları, stokları, müşterileri ve faturaları tek bir güçlü platformdan yönetin. Perakende mağazalar, restoranlar ve büyüyen işletmeler için mükemmel çözüm."
    },
    "Start Your Free Trial": {
        "hi": "अपना निःशुल्क परीक्षण शुरू करें",
        "ar": "ابدأ تجربتك المجانية",
        "es": "Comienza Tu Prueba Gratis",
        "fr": "Commencer votre essai gratuit",
        "pt": "Iniciar Teste Grátis",
        "de": "Kostenlose Testversion starten",
        "zh": "开启免费试用",
        "ja": "無料トライアルを開始",
        "ru": "Начать бесплатный пробный период",
        "it": "Inizia la Prova Gratuita",
        "id": "Mulai Uji Coba Gratis",
        "tr": "Ücretsiz Denemenizi Başlatın"
    },
    "Start Free Trial": {
        "hi": "निःशुल्क ट्रायल शुरू करें",
        "ar": "ابدأ التجربة المجانية",
        "es": "Iniciar Prueba Gratis",
        "fr": "Essai gratuit",
        "pt": "Iniciar Teste Grátis",
        "de": "Kostenlos testen",
        "zh": "免费试用",
        "ja": "無料トライアル",
        "ru": "Бесплатная пробная версия",
        "it": "Prova Gratis",
        "id": "Coba Gratis",
        "tr": "Ücretsiz Deneyin"
    },
    "Watch Demo": {
        "hi": "डेमो देखें",
        "ar": "مشاهدة العرض التجريبي",
        "es": "Ver Demostración",
        "fr": "Voir la démo",
        "pt": "Ver Demonstração",
        "de": "Demo ansehen",
        "zh": "观看演示",
        "ja": "デモを見る",
        "ru": "Смотреть демо",
        "it": "Guarda la Demo",
        "id": "Lihat Demo",
        "tr": "Demoyu İzleyin"
    },
    "Explore Pricing": {
        "hi": "मूल्य निर्धारण देखें",
        "ar": "استكشف خطط الأسعار",
        "es": "Explorar Precios",
        "fr": "Découvrir les tarifs",
        "pt": "Explorar Preços",
        "de": "Preise ansehen",
        "zh": "查看价格方案",
        "ja": "料金を見る",
        "ru": "Посмотреть тарифы",
        "it": "Scopri i Prezzi",
        "id": "Lihat Daftar Harga",
        "tr": "Fiyatları İnceleyin"
    },
    "No credit card required": {
        "hi": "क्रेडिट कार्ड की आवश्यकता नहीं",
        "ar": "لا يلزم بطاقة ائتمان",
        "es": "No requiere tarjeta de crédito",
        "fr": "Aucune carte bancaire requise",
        "pt": "Sem necessidade de cartão de crédito",
        "de": "Keine Kreditkarte erforderlich",
        "zh": "无需信用卡",
        "ja": "クレジットカード登録不要",
        "ru": "Банковская карта не требуется",
        "it": "Nessuna carta di credito richiesta",
        "id": "Tanpa kartu kredit",
        "tr": "Kredi kartı gerekmez"
    },
    "Instant setup": {
        "hi": "तत्काल सेटअप",
        "ar": "إعداد فوري",
        "es": "Configuración instantánea",
        "fr": "Configuration instantanée",
        "pt": "Configuração instantânea",
        "de": "Sofortige Einrichtung",
        "zh": "即开即用",
        "ja": "即時セットアップ",
        "ru": "Мгновенная настройка",
        "it": "Attivazione istantanea",
        "id": "Penyiapan instan",
        "tr": "Anında kurulum"
    },
    "COMPATIBLE WITH STANDARD RETAIL & RESTAURANT HARDWARE": {
        "hi": "मानक रिटेल और रेस्तरां हार्डवेयर के साथ पूरी तरह अनुकूल",
        "ar": "متوافق تماماً مع جميع أجهزة نقاط البيع القياسية للتجزئة والمطاعم",
        "es": "COMPATIBLE CON HARDWARE ESTÁNDAR DE RETAIL Y RESTAURACIÓN",
        "fr": "COMPATIBLE AVEC LE MATÉRIEL STANDARD DE COMMERCE ET RESTAURATION",
        "pt": "COMPATÍVEL COM HARDWARE PADRÃO DE VAREJO E RESTAURANTES",
        "de": "KOMPATIBEL MIT STANDARD-HARDWARE FÜR HANDEL & GASTRONOMIE",
        "zh": "完美兼容主流零售及餐饮行业收银硬件外设",
        "ja": "標準的な小売・飲食店向けハードウェアに完全対応",
        "ru": "СОВМЕСТИМО СО СТАНДАРТНЫМ ТОРГОВЫМ И КАССОВЫМ ОБОРУДОВАНИЕМ",
        "it": "COMPATIBILE CON HARDWARE STANDARD PER COMMERCIO E RISTORAZIONE",
        "id": "KOMPATIBEL DENGAN PERANGKAT KERAS RITEL & RESTORAN STANDAR",
        "tr": "STANDART PERAKENDE VE RESTORAN DONANIMLARIYLA TAM UYUMLU"
    },
    "Instant Zero-Latency Read": {
        "hi": "शून्य विलंबता त्वरित स्कैन",
        "ar": "قراءة فورية بدون أي تأخير",
        "es": "Lectura instantánea sin latencia",
        "fr": "Lecture instantanée sans latence",
        "pt": "Leitura instantânea com latência zero",
        "de": "Verzögerungsfreie Sofort-Erfassung",
        "zh": "零延迟秒速扫码",
        "ja": "ゼロ遅延の超高速読み取り",
        "ru": "Мгновенное сканирование без задержек",
        "it": "Lettura istantanea a latenza zero",
        "id": "Pemindaian instan tanpa jeda",
        "tr": "Sıfır gecikmeli anında okuma"
    },
    "58mm & 80mm ESC/POS": {
        "hi": "58 मिमी और 80 मिमी ESC/POS",
        "ar": "طابعات حرارية 58 ملم و80 ملم ESC/POS",
        "es": "Térmica 58mm y 80mm ESC/POS",
        "fr": "Thermique 58mm & 80mm ESC/POS",
        "pt": "Térmica 58mm e 80mm ESC/POS",
        "de": "Thermodruck 58mm & 80mm ESC/POS",
        "zh": "58mm与80mm热敏小票机",
        "ja": "58mm/80mm レシートプリンター",
        "ru": "Термопринтеры 58мм и 80мм ESC/POS",
        "it": "Termica 58mm & 80mm ESC/POS",
        "id": "Printer termal 58mm & 80mm ESC/POS",
        "tr": "58mm ve 80mm Termal ESC/POS"
    },
    "UPI, EMV, Contactless & NFC": {
        "hi": "यूपीआई, ईएमवी, कॉन्टैक्टलेस और एनएफसी",
        "ar": "مدفوعات لا تلامسية، بطاقات ذكية وNFC",
        "es": "Tarjetas EMV, Contactless y NFC",
        "fr": "Cartes EMV, sans contact et NFC",
        "pt": "Cartões EMV, por aproximação e NFC",
        "de": "Kontaktlos, EMV-Chip & NFC",
        "zh": "刷卡芯片、无接触支付与NFC",
        "ja": "ICカード、タッチ決済＆NFC対応",
        "ru": "Бесконтактная оплата, чип и NFC",
        "it": "Carte EMV, contactless ed NFC",
        "id": "Kartu chip, nirsentuh & NFC",
        "tr": "Temassız, EMV çipli kart ve NFC"
    },
    "Automated Kick-Open Pulse": {
        "hi": "स्वचालित किक-ओपन पल्स",
        "ar": "فتح تلقائي لصندوق النقدية",
        "es": "Apertura automática de cajón portamonedas",
        "fr": "Ouverture automatique du tiroir-caisse",
        "pt": "Abertura automática de gaveta de dinheiro",
        "de": "Automatischer Kassenladen-Impuls",
        "zh": "结账自动弹开钱箱",
        "ja": "キャッシュドロワー自動オープン連動",
        "ru": "Автоматическое открытие денежного ящика",
        "it": "Apertura automatica del cassetto contanti",
        "id": "Pembukaan laci kasir otomatis",
        "tr": "Otomatik para çekmecesi açma darbesi"
    },
    "Live KDS & Dispatch Screen": {
        "hi": "लाइव केडीएस और डिस्पैच स्क्रीन",
        "ar": "شاشة المطبخ KDS وشاشة تسليم الطلبات المباشرة",
        "es": "Pantalla de Cocina KDS y Despacho en Vivo",
        "fr": "Écran Cuisine KDS & Affichage des Commandes",
        "pt": "Tela de Cozinha KDS e Expedição ao Vivo",
        "de": "Live Küchenanzeige KDS & Ausgabe-Bildschirm",
        "zh": "后厨KDS实时看板与出餐呼叫大屏",
        "ja": "キッチンディスプレイ(KDS)＆呼出画面",
        "ru": "Кухонный экран KDS и монитор выдачи заказов",
        "it": "Schermo Cucina KDS e Monitor Spedizioni",
        "id": "Layar Dapur KDS & Tampilan Pengiriman Langsung",
        "tr": "Canlı Mutfak Ekranı KDS ve Sevkiyat Monitörü"
    },
    "Tailored for Your Industry": {
        "hi": "आपके उद्योग के लिए विशेष रूप से निर्मित",
        "ar": "مصمم خصيصاً ليناسب طبيعة نشاطك التجاري",
        "es": "Diseñado para tu industria",
        "fr": "Conçu sur mesure pour votre secteur",
        "pt": "Sob medida para o seu setor",
        "de": "Maßgeschneidert für Ihre Branche",
        "zh": "为您的行业量身定制",
        "ja": "業種ごとの専用ソリューション",
        "ru": "Специализированные решения для вашей отрасли",
        "it": "Su misura per il tuo settore",
        "id": "Disesuaikan untuk Industri Anda",
        "tr": "Sektörünüze Özel Çözümler"
    },
    "Specialized workflows and capabilities engineered for retail, food & beverage, supermarkets, and service operations.": {
        "hi": "खुदरा, खाद्य और पेय पदार्थ, सुपरमार्केट और सेवा संचालन के लिए तैयार की गई विशेष कार्यप्रणाली और क्षमताएं।",
        "ar": "سير عمل وإمكانيات مخصصة مصممة لتجارة التجزئة، والمطاعم والمقاهي، والسوبر ماركت، ومتاجر الخدمات.",
        "es": "Flujos de trabajo especializados diseñados para comercio minorista, hostelería, supermercados y empresas de servicios.",
        "fr": "Des processus et des outils conçus spécialement pour le commerce de détail, la restauration, les supermarchés et les services.",
        "pt": "Fluxos de trabalho e recursos especializados desenvolvidos para varejo, alimentação, supermercados e serviços.",
        "de": "Spezialisierte Arbeitsabläufe für Einzelhandel, Gastronomie, Supermärkte und Dienstleister.",
        "zh": "为零售精品、餐饮咖啡、大型商超与专业服务定制的专业工作流与业务能力。",
        "ja": "小売店、飲食・カフェ、スーパーマーケット、サービス業向けに最適化された専用ワークフロー。",
        "ru": "Оптимизированные рабочие процессы и инструменты для розницы, общепита, супермаркетов и сферы услуг.",
        "it": "Flussi di lavoro dedicati a retail, ristorazione, supermercati e aziende di servizi.",
        "id": "Alur kerja dan kemampuan khusus yang dirancang untuk ritel, makanan & minuman, supermarket, dan jasa.",
        "tr": "Perakende, yiyecek & içecek, süpermarketler ve hizmet işletmeleri için özel olarak tasarlanmış iş akışları."
    },
    "Enterprise-Grade Modules": {
        "hi": "उद्यम-स्तरीय मॉड्यूल",
        "ar": "وحدات تشغيلية بمستوى المؤسسات الكبرى",
        "es": "Módulos de Nivel Empresarial",
        "fr": "Modules d'entreprise avancés",
        "pt": "Módulos de Nível Empresarial",
        "de": "Module auf Enterprise-Niveau",
        "zh": "企业级核心功能模块",
        "ja": "エンタープライズ対応の機能モジュール",
        "ru": "Модули корпоративного уровня",
        "it": "Moduli di Livello Enterprise",
        "id": "Modul Tingkat Perusahaan",
        "tr": "Kurumsal Düzeyde Modüller"
    },
    "Enterprise-Grade Features": {
        "hi": "उद्यम-स्तरीय शक्तिशाली सुविधाएँ",
        "ar": "مميزات تقنية بمستوى الشركات العالمية",
        "es": "Características de Grado Empresarial",
        "fr": "Fonctionnalités de niveau entreprise",
        "pt": "Recursos de Classe Corporativa",
        "de": "Funktionen für Unternehmen",
        "zh": "企业级全能业务特性",
        "ja": "エンタープライズクラスの機能群",
        "ru": "Возможности корпоративного класса",
        "it": "Caratteristiche di Livello Enterprise",
        "id": "Fitur Andal Skala Perusahaan",
        "tr": "Kurumsal Ölçekte Özellikler"
    },
    "Comprehensive suite of integrated business applications that scale from a single shop to multi-chain enterprises.": {
        "hi": "एकीकृत व्यावसायिक अनुप्रयोगों का व्यापक सेट जो एक छोटी दुकान से लेकर बहु-शृंखला वाले उद्यमों तक आसानी से बढ़ता है।",
        "ar": "مجموعة شاملة من التطبيقات التجارية المتكاملة تتوسع معك من متجر واحد إلى شبكة فروع ضخمة.",
        "es": "Conjunto integral de aplicaciones empresariales integradas que escalan desde una tienda hasta cadenas multinacionales.",
        "fr": "Une suite complète d'applications métier intégrées qui s'adaptent de la boutique individuelle aux réseaux d'enseignes.",
        "pt": "Conjunto completo de aplicativos de negócios integrados que crescem de uma única loja até redes de franquias.",
        "de": "Eine umfassende Suite integrierter Geschäftsanwendungen, die vom Einzelladen bis zur Großfiliale skaliert.",
        "zh": "从单店零售到大型连锁跨区域集团，满足企业各个发展周期的全流程一体化应用套件。",
        "ja": "個人店舗から全国チェーン展開まで柔軟にスケールする包括的ビジネスアプリケーション群。",
        "ru": "Полный комплекс интегрированных бизнес-приложений, масштабируемых от одной торговой точки до федеральных сетей.",
        "it": "Suite completa di applicazioni aziendali integrate che scalano dal singolo negozio alla grande catena.",
        "id": "Rangkaian aplikasi bisnis terintegrasi yang dapat berkembang dari satu toko kecil hingga jaringan multi-cabang.",
        "tr": "Tek bir dükkandan zincir mağazalara kadar ölçeklenebilen kapsamlı entegre iş uygulamaları paketi."
    },
    "Simple, Transparent Pricing": {
        "hi": "सरल और पारदर्शी मूल्य निर्धारण",
        "ar": "أسعار واضحة وبسيطة وشفافة",
        "es": "Precios Simples y Transparentes",
        "fr": "Tarification simple et transparente",
        "pt": "Preços Simples e Transparentes",
        "de": "Einfache, transparente Preisgestaltung",
        "zh": "简单、透明、无隐形收费的价格方案",
        "ja": "シンプルでわかりやすい料金体系",
        "ru": "Простые и прозрачные тарифные планы",
        "it": "Prezzi Semplici e Trasparenti",
        "id": "Harga Sederhana & Transparan",
        "tr": "Basit, Şeffaf Fiyatlandırma"
    },
    "Choose the plan that fits your business stage. Upgrade, downgrade, or cancel anytime.": {
        "hi": "अपने व्यवसाय के अनुकूल योजना चुनें। किसी भी समय अपग्रेड, डाउनग्रेड या रद्द करें।",
        "ar": "اختر الخطة المناسبة لحجم أعمالك. يمكنك الترقية أو التخفيض أو الإلغاء في أي وقت بكل سهولة.",
        "es": "Elige el plan adecuado para tu negocio. Cambia o cancela en cualquier momento.",
        "fr": "Choisissez l'offre adaptée à votre entreprise. Modifiez ou résiliez à tout moment.",
        "pt": "Escolha o plano ideal para a sua empresa. Atualize ou cancele a qualquer momento.",
        "de": "Wählen Sie das passende Modell für Ihr Unternehmen. Jederzeit flexibel anpassbar.",
        "zh": "自由选择契合企业规模的方案。随时随地升级、降级或取消，零门槛保障。",
        "ja": "ビジネスの成長段階に合わせたプランを選択。いつでも変更や解約が可能です。",
        "ru": "Выберите тариф, подходящий для вашего этапа развития. Изменяйте или отменяйте подписку в любое время.",
        "it": "Scegli il piano ideale per la tua attività. Modifica o annulla in qualsiasi momento.",
        "id": "Pilih paket yang sesuai dengan bisnis Anda. Tingkatkan atau batalkan kapan saja.",
        "tr": "İşletmenizin ölçeğine uygun planı seçin. Dilediğiniz zaman yükseltin veya iptal edin."
    },
    "Monthly": {
        "hi": "मासिक",
        "ar": "شهرياً",
        "es": "Mensual",
        "fr": "Mensuel",
        "pt": "Mensal",
        "de": "Monatlich",
        "zh": "按月付费",
        "ja": "月払い",
        "ru": "Ежемесячно",
        "it": "Mensile",
        "id": "Bulanan",
        "tr": "Aylık"
    },
    "Yearly (Save 20%)": {
        "hi": "वार्षिक (20% की बचत)",
        "ar": "سنوياً (وفر 20%)",
        "es": "Anual (Ahorra 20%)",
        "fr": "Annuel (-20% d'économie)",
        "pt": "Anual (Economize 20%)",
        "de": "Jährlich (20% sparen)",
        "zh": "按年付费（省 20%）",
        "ja": "年払い（20%お得）",
        "ru": "Ежегодно (скидка 20%)",
        "it": "Annuale (Risparmia il 20%)",
        "id": "Tahunan (Hemat 20%)",
        "tr": "Yıllık (%20 Tasarruf)"
    },
    "Yearly": {
        "hi": "वार्षिक",
        "ar": "سنوياً",
        "es": "Anual",
        "fr": "Annuel",
        "pt": "Anual",
        "de": "Jährlich",
        "zh": "按年",
        "ja": "年払い",
        "ru": "Ежегодно",
        "it": "Annuale",
        "id": "Tahunan",
        "tr": "Yıllık"
    },
    "Save 20%": {
        "hi": "20% बचाएं",
        "ar": "وفر 20%",
        "es": "Ahorra 20%",
        "fr": "Économisez 20%",
        "pt": "Economize 20%",
        "de": "20% sparen",
        "zh": "立省20%",
        "ja": "20%割引",
        "ru": "Экономия 20%",
        "it": "Risparmia il 20%",
        "id": "Hemat 20%",
        "tr": "%20 Tasarruf"
    },
    "/month": {
        "hi": "/माह",
        "ar": "/شهرياً",
        "es": "/mes",
        "fr": "/mois",
        "pt": "/mês",
        "de": "/Monat",
        "zh": "/月",
        "ja": "/月",
        "ru": "/мес",
        "it": "/mese",
        "id": "/bulan",
        "tr": "/ay"
    },
    "/year": {
        "hi": "/वर्ष",
        "ar": "/سنوياً",
        "es": "/año",
        "fr": "/an",
        "pt": "/ano",
        "de": "/Jahr",
        "zh": "/年",
        "ja": "/年",
        "ru": "/год",
        "it": "/anno",
        "id": "/tahun",
        "tr": "/yıl"
    },
    "Starter": {
        "hi": "स्टार्टर",
        "ar": "البداية (ستارتر)",
        "es": "Starter",
        "fr": "Starter",
        "pt": "Iniciante",
        "de": "Starter",
        "zh": "初创版",
        "ja": "スターター",
        "ru": "Базовый",
        "it": "Base",
        "id": "Pemula",
        "tr": "Başlangıç"
    },
    "Professional": {
        "hi": "प्रोफेशनल",
        "ar": "الاحترافي (برو)",
        "es": "Profesional",
        "fr": "Professionnel",
        "pt": "Profissional",
        "de": "Professional",
        "zh": "专业版",
        "ja": "プロフェッショナル",
        "ru": "Профессиональный",
        "it": "Professionale",
        "id": "Profesional",
        "tr": "Profesyonel"
    },
    "Enterprise": {
        "hi": "एंटरप्राइज़",
        "ar": "المؤسسات الكبرى",
        "es": "Empresarial",
        "fr": "Entreprise",
        "pt": "Empresarial",
        "de": "Enterprise",
        "zh": "旗舰企业版",
        "ja": "エンタープライズ",
        "ru": "Корпоративный",
        "it": "Enterprise",
        "id": "Korporat",
        "tr": "Kurumsal"
    },
    "Most Popular": {
        "hi": "सर्वाधिक लोकप्रिय",
        "ar": "الأكثر طلباً وشهرة",
        "es": "Más Popular",
        "fr": "Le plus populaire",
        "pt": "Mais Popular",
        "de": "Am beliebtesten",
        "zh": "最受欢迎",
        "ja": "一番人気",
        "ru": "Самый популярный",
        "it": "Più Popolare",
        "id": "Paling Populer",
        "tr": "En Popüler"
    },
    "Popular": {
        "hi": "लोकप्रिय",
        "ar": "شائع",
        "es": "Popular",
        "fr": "Populaire",
        "pt": "Popular",
        "de": "Beliebt",
        "zh": "热门推荐",
        "ja": "人気",
        "ru": "Популярный",
        "it": "Popolare",
        "id": "Populer",
        "tr": "Popüler"
    },
    "Contact Sales": {
        "hi": "बिक्री टीम से संपर्क करें",
        "ar": "تواصل مع فريق المبيعات",
        "es": "Contactar con Ventas",
        "fr": "Contacter l'équipe commerciale",
        "pt": "Fale com Vendas",
        "de": "Vertrieb kontaktieren",
        "zh": "联系销售顾问",
        "ja": "営業にお問い合わせ",
        "ru": "Связаться с отделом продаж",
        "it": "Contatta le Vendite",
        "id": "Hubungi Tim Penjualan",
        "tr": "Satış Ekibiyle İletişime Geçin"
    },
    "Frequently Asked Questions": {
        "hi": "अक्सर पूछे जाने वाले प्रश्न",
        "ar": "الأسئلة الشائعة والأجوبة",
        "es": "Preguntas Frecuentes",
        "fr": "Foire Aux Questions",
        "pt": "Perguntas Frequentes",
        "de": "Häufig gestellte Fragen",
        "zh": "常见问题解答",
        "ja": "よくあるご質問",
        "ru": "Часто задаваемые вопросы",
        "it": "Domande Frequenti",
        "id": "Pertanyaan yang Sering Diajukan",
        "tr": "Sıkça Sorulan Sorular"
    },
    "Everything you need to know about scaling online sales, hardware setup, and offline POS reliability.": {
        "hi": "ऑनलाइन बिक्री बढ़ाने, हार्डवेयर सेटअप और ऑफ़लाइन पीओएस विश्वसनीयता के बारे में आपको जो कुछ जानने की ज़रूरत है।",
        "ar": "كل ما تحتاج لمعرفته حول تنمية المبيعات وإعداد الأجهزة والعمل بدون إنترنت بموثوقية كاملة.",
        "es": "Todo lo que necesitas saber sobre ventas en línea, hardware y fiabilidad del TPV sin conexión.",
        "fr": "Tout ce que vous devez savoir sur le développement des ventes, le matériel et la fiabilité hors ligne.",
        "pt": "Tudo o que você precisa saber sobre vendas online, configuração de hardware e PDV offline.",
        "de": "Alles, was Sie über Online-Verkäufe, Hardware-Setup und Offline-Kassensicherheit wissen müssen.",
        "zh": "关于线上销售拓展、收银硬件配置与离线无网收银可靠性的全方位解答。",
        "ja": "オンライン販売の拡大、ハードウェア設定、オフラインPOSの信頼性について必要なすべての情報。",
        "ru": "Всё, что вам нужно знать о масштабировании продаж, подключении оборудования и надёжной офлайн-работе.",
        "it": "Tutto ciò che devi sapere su vendite online, hardware e affidabilità del POS offline.",
        "id": "Semua yang perlu Anda ketahui tentang penjualan online, setup perangkat, dan POS offline.",
        "tr": "Online satışları ölçeklendirme, donanım kurulumu ve çevrimdışı POS güvenilirliği hakkında bilmeniz gereken her şey."
    },
    "Get in Touch": {
        "hi": "संपर्क करें",
        "ar": "تواصل معنا مباشرة",
        "es": "Ponte en Contacto",
        "fr": "Prenez contact avec nous",
        "pt": "Entre em Contato",
        "de": "Treten Sie mit uns in Kontakt",
        "zh": "随时与我们取得联系",
        "ja": "お問い合わせはこちら",
        "ru": "Свяжитесь с нами",
        "it": "Mettiti in Contatto",
        "id": "Hubungi Kami",
        "tr": "Bizimle İletişime Geçin"
    },
    "Have questions or need a custom enterprise solution? Our team is ready to help.": {
        "hi": "क्या आपके कोई प्रश्न हैं या आपको एक विशेष एंटरप्राइज़ समाधान चाहिए? हमारी टीम मदद के लिए तैयार है।",
        "ar": "هل لديك استفسار أو تحتاج إلى حل مخصص لشركتك؟ فريقنا الخبير جاهز لخدمتك على الفور.",
        "es": "¿Tienes preguntas o necesitas una solución a medida? Nuestro equipo está listo para ayudarte.",
        "fr": "Vous avez des questions ou besoin d'une solution sur mesure ? Notre équipe est à votre écoute.",
        "pt": "Tem dúvidas ou precisa de uma solução corporativa sob medida? Nossa equipe está pronta para ajudar.",
        "de": "Haben Sie Fragen oder benötigen Sie eine maßgeschneiderte Lösung? Wir beraten Sie gerne.",
        "zh": "有任何疑问或需要企业级专属定制方案？我们的专家团队随时准备为您服务。",
        "ja": "ご質問やカスタムプランのご相談など、専任チームがお手伝いいたします。",
        "ru": "Есть вопросы или требуется индивидуальное решение? Наша команда готова помочь.",
        "it": "Hai domande o hai bisogno di una soluzione aziendale personalizzata? Il nostro team è pronto ad aiutarti.",
        "id": "Ada pertanyaan atau butuh solusi khusus untuk bisnis Anda? Tim kami siap membantu.",
        "tr": "Sorularınız mı var veya özel bir kurumsal çözüme mi ihtiyacınız var? Ekibimiz yardıma hazır."
    },
    "Your Name": {
        "hi": "आपका नाम",
        "ar": "الاسم بالكامل",
        "es": "Tu Nombre",
        "fr": "Votre Nom",
        "pt": "Seu Nome",
        "de": "Ihr Name",
        "zh": "您的姓名",
        "ja": "お名前",
        "ru": "Ваше имя",
        "it": "Il tuo nome",
        "id": "Nama Anda",
        "tr": "Adınız Soyadınız"
    },
    "Full Name": {
        "hi": "पूरा नाम",
        "ar": "الاسم الكامل",
        "es": "Nombre Completo",
        "fr": "Nom complet",
        "pt": "Nome Completo",
        "de": "Vollständiger Name",
        "zh": "完整姓名",
        "ja": "氏名",
        "ru": "Полное имя",
        "it": "Nome Completo",
        "id": "Nama Lengkap",
        "tr": "Ad Soyad"
    },
    "Email Address": {
        "hi": "ईमेल पता",
        "ar": "البريد الإلكتروني",
        "es": "Correo Electrónico",
        "fr": "Adresse e-mail",
        "pt": "Endereço de E-mail",
        "de": "E-Mail-Adresse",
        "zh": "电子邮箱",
        "ja": "メールアドレス",
        "ru": "Электронная почта",
        "it": "Indirizzo Email",
        "id": "Alamat Email",
        "tr": "E-posta Adresi"
    },
    "Phone Number": {
        "hi": "फ़ोन नंबर",
        "ar": "رقم الهاتف",
        "es": "Número de Teléfono",
        "fr": "Numéro de téléphone",
        "pt": "Número de Telefone",
        "de": "Telefonnummer",
        "zh": "联系电话",
        "ja": "電話番号",
        "ru": "Номер телефона",
        "it": "Numero di Telefono",
        "id": "Nomor Telepon",
        "tr": "Telefon Numarası"
    },
    "Business Type": {
        "hi": "व्यवसाय का प्रकार",
        "ar": "نوع النشاط التجاري",
        "es": "Tipo de Negocio",
        "fr": "Type d'entreprise",
        "pt": "Tipo de Negócio",
        "de": "Geschäftsart",
        "zh": "所属行业类型",
        "ja": "業種・業態",
        "ru": "Тип бизнеса",
        "it": "Tipo di Attività",
        "id": "Jenis Usaha",
        "tr": "İşletme Türü"
    },
    "Store Type": {
        "hi": "स्टोर का प्रकार",
        "ar": "نوع المتجر",
        "es": "Tipo de Tienda",
        "fr": "Type de magasin",
        "pt": "Tipo de Loja",
        "de": "Filialtyp",
        "zh": "门店类型",
        "ja": "店舗形態",
        "ru": "Тип магазина",
        "it": "Tipo di Negozio",
        "id": "Tipe Toko",
        "tr": "Mağaza Türü"
    },
    "Subject": {
        "hi": "विषय",
        "ar": "الموضوع",
        "es": "Asunto",
        "fr": "Sujet",
        "pt": "Assunto",
        "de": "Betreff",
        "zh": "主题",
        "ja": "件名",
        "ru": "Тема сообщения",
        "it": "Oggetto",
        "id": "Subjek",
        "tr": "Konu"
    },
    "Your Message": {
        "hi": "आपका संदेश",
        "ar": "رسالتك",
        "es": "Tu Mensaje",
        "fr": "Votre Message",
        "pt": "Sua Mensagem",
        "de": "Ihre Nachricht",
        "zh": "留言内容",
        "ja": "お問い合わせ内容",
        "ru": "Ваше сообщение",
        "it": "Il tuo messaggio",
        "id": "Pesan Anda",
        "tr": "Mesajınız"
    },
    "Message": {
        "hi": "संदेश",
        "ar": "الرسالة",
        "es": "Mensaje",
        "fr": "Message",
        "pt": "Mensagem",
        "de": "Nachricht",
        "zh": "信息",
        "ja": "メッセージ",
        "ru": "Сообщение",
        "it": "Messaggio",
        "id": "Pesan",
        "tr": "Mesaj"
    },
    "Send Message": {
        "hi": "संदेश भेजें",
        "ar": "إرسال الرسالة",
        "es": "Enviar Mensaje",
        "fr": "Envoyer le message",
        "pt": "Enviar Mensagem",
        "de": "Nachricht senden",
        "zh": "发送消息",
        "ja": "送信する",
        "ru": "Отправить сообщение",
        "it": "Invia Messaggio",
        "id": "Kirim Pesan",
        "tr": "Mesaj Gönder"
    },
    "Send Inquiry": {
        "hi": "पूछताछ भेजें",
        "ar": "إرسال طلب الاستفسار",
        "es": "Enviar Consulta",
        "fr": "Envoyer la demande",
        "pt": "Enviar Solicitação",
        "de": "Anfrage senden",
        "zh": "提交咨询",
        "ja": "問い合わせを送信",
        "ru": "Отправить заявку",
        "it": "Invia Richiesta",
        "id": "Kirim Pertanyaan",
        "tr": "Talep Gönder"
    },
    "Sending...": {
        "hi": "भेजा जा रहा है...",
        "ar": "جارٍ الإرسال...",
        "es": "Enviando...",
        "fr": "Envoi en cours...",
        "pt": "Enviando...",
        "de": "Wird gesendet...",
        "zh": "正在发送...",
        "ja": "送信中...",
        "ru": "Отправка...",
        "it": "Invio in corso...",
        "id": "Mengirim...",
        "tr": "Gönderiliyor..."
    },
    "Thank you for reaching out! Our team will contact you shortly.": {
        "hi": "संपर्क करने के लिए धन्यवाद! हमारी टीम शीघ्र ही आपसे संपर्क करेगी।",
        "ar": "شكراً لتواصلك معنا! سيقوم فريقنا بالرد عليك في أقرب وقت ممكن.",
        "es": "¡Gracias por contactarnos! Nuestro equipo se pondrá en contacto pronto.",
        "fr": "Merci de nous avoir contactés ! Notre équipe reviendra vers vous très vite.",
        "pt": "Obrigado por entrar em contato! Nossa equipe responderá em breve.",
        "de": "Vielen Dank für Ihre Nachricht! Unser Team wird sich in Kürze bei Ihnen melden.",
        "zh": "感谢您的咨询！我们的团队将尽快与您取得联系。",
        "ja": "お問い合わせありがとうございます！担当者より追ってご連絡いたします。",
        "ru": "Спасибо за обращение! Наш специалист свяжется с вами в ближайшее время.",
        "it": "Grazie per averci contattato! Il nostro team ti ricontatterà al più presto.",
        "id": "Terima kasih telah menghubungi kami! Tim kami akan segera merespons.",
        "tr": "Bizimle iletişime geçtiğiniz için teşekkürler! Ekibimiz en kısa sürede size dönüş yapacaktır."
    },
    "Retail & Supermarket": {
        "hi": "खुदरा और सुपरमार्केट",
        "ar": "متاجر التجزئة والسوبر ماركت",
        "es": "Comercio Minorista y Supermercado",
        "fr": "Commerce de détail et Supermarché",
        "pt": "Varejo e Supermercado",
        "de": "Einzelhandel & Supermarkt",
        "zh": "零售百货与生鲜商超",
        "ja": "小売店・スーパーマーケット",
        "ru": "Розница и супермаркеты",
        "it": "Retail e Supermercati",
        "id": "Ritel & Supermarket",
        "tr": "Perakende & Süpermarket"
    },
    "Restaurant / Cafe / QSR": {
        "hi": "रेस्तरां / कैफे / क्यूएसआर",
        "ar": "مطاعم / مقاهي / وجبات سريعة",
        "es": "Restaurante / Cafetería / Fast Food",
        "fr": "Restaurant / Café / Restauration Rapide",
        "pt": "Restaurante / Café / Fast Food",
        "de": "Restaurant / Café / Schnellrestaurant",
        "zh": "连锁餐饮 / 咖啡馆 / 快餐厅",
        "ja": "レストラン / カフェ / ファストフード",
        "ru": "Рестораны / Кафе / Фастфуд",
        "it": "Ristoranti / Bar / Fast Food",
        "id": "Restoran / Kafe / Cepat Saji",
        "tr": "Restoran / Kafe / Fast Food"
    },
    "Salon & Spa": {
        "hi": "सैलून और स्पा",
        "ar": "صالونات التجميل والسبا",
        "es": "Salón de Belleza y Spa",
        "fr": "Salon de coiffure et Spa",
        "pt": "Salão de Beleza e Spa",
        "de": "Salon & Spa",
        "zh": "美发美甲与养生SPA",
        "ja": "サロン＆スパ",
        "ru": "Салоны красоты и СПА",
        "it": "Salone di Bellezza e Spa",
        "id": "Salon & Spa",
        "tr": "Kuaför & Spa"
    },
    "Pharmacy": {
        "hi": "फार्मेसी / दवाखाना",
        "ar": "الصيدليات والمستلزمات الطبية",
        "es": "Farmacia",
        "fr": "Pharmacie",
        "pt": "Farmácia",
        "de": "Apotheke",
        "zh": "药房药店与医药保健",
        "ja": "薬局・ドラッグストア",
        "ru": "Аптеки и оптика",
        "it": "Farmacia",
        "id": "Farmasi / Apotek",
        "tr": "Eczane & Medikal"
    },
    "Service Business": {
        "hi": "सेवा व्यवसाय",
        "ar": "الشركات الخدمية",
        "es": "Empresa de Servicios",
        "fr": "Entreprise de services",
        "pt": "Empresas de Serviços",
        "de": "Dienstleistungsbetrieb",
        "zh": "生活与专业服务业",
        "ja": "各種サービス業",
        "ru": "Сфера услуг и сервиса",
        "it": "Aziende di Servizi",
        "id": "Bisnis Layanan Jasa",
        "tr": "Hizmet İşletmeleri"
    },
    "Other": {
        "hi": "अन्य",
        "ar": "أخرى",
        "es": "Otro",
        "fr": "Autre",
        "pt": "Outro",
        "de": "Sonstiges",
        "zh": "其他行业",
        "ja": "その他",
        "ru": "Другое",
        "it": "Altro",
        "id": "Lainnya",
        "tr": "Diğer"
    },
    "All rights reserved.": {
        "hi": "सर्वाधिकार सुरक्षित।",
        "ar": "جميع الحقوق محفوظة.",
        "es": "Todos los derechos reservados.",
        "fr": "Tous droits réservés.",
        "pt": "Todos os direitos reservados.",
        "de": "Alle Rechte vorbehalten.",
        "zh": "版权所有，保留一切权利。",
        "ja": "全著作権所有。",
        "ru": "Все права защищены.",
        "it": "Tutti i diritti riservati.",
        "id": "Hak cipta dilindungi undang-undang.",
        "tr": "Tüm hakları saklıdır."
    }
}

print(f"Total keys configured: {len(translations)}")

# 1. Update lang/*.json in Laravel so backend API serves them
for loc in ['ar', 'es', 'hi', 'fr', 'pt', 'de', 'zh', 'ja', 'ru', 'it', 'id', 'tr']:
    path = f"lang/{loc}.json"
    if os.path.exists(path):
        with open(path, 'r', encoding='utf-8') as f:
            data = json.load(f)
        added = 0
        for k, v in translations.items():
            if loc in v:
                data[k] = v[loc]
                added += 1
        with open(path, 'w', encoding='utf-8') as f:
            json.dump(data, f, ensure_ascii=False, indent=4)
        print(f"Updated {path} with {added} keys.")

# 2. Generate mobile/lib/features/landing/models/landing_translations.dart
out_path = "mobile/lib/features/landing/models/landing_translations.dart"
os.makedirs(os.path.dirname(out_path), exist_ok=True)

with open(out_path, "w", encoding="utf-8") as f:
    f.write("""import '../../../../core/services/translations_cache.dart';

/// Bundled zero-latency landing translations for 13 supported languages.
/// Provides immediate fallback translations before or alongside TranslationsCache refresh.
class LandingTranslations {
  LandingTranslations._();

  static String tr(String text, String langCode) {
    if (text.isEmpty) return text;
    final code = langCode.trim().toLowerCase().split(RegExp(r'[-_]')).first;
    if (code == 'en') {
      return TranslationsCache.instance.forLocale('en')[text] ?? text;
    }

    // 1. Check live TranslationsCache (populated from Laravel /api/app/translations)
    final cached = TranslationsCache.instance.forLocale(code)[text];
    if (cached != null && cached.trim().isNotEmpty) {
      return cached;
    }

    // 2. Check bundled landing translations
    final bundled = _translations[code]?[text];
    if (bundled != null && bundled.trim().isNotEmpty) {
      return bundled;
    }

    // 3. Fallback to English original text
    return text;
  }

  static const Map<String, Map<String, String>> _translations = {
""")
    for loc in ['ar', 'es', 'hi', 'fr', 'pt', 'de', 'zh', 'ja', 'ru', 'it', 'id', 'tr']:
        f.write(f"    '{loc}': {{\n")
        for k, v in translations.items():
            if loc in v:
                val = v[loc].replace('\\', '\\\\').replace("'", "\\'").replace('$', '\\$')
                key_escaped = k.replace('\\', '\\\\').replace("'", "\\'").replace('$', '\\$')
                f.write(f"      '{key_escaped}': '{val}',\n")
        f.write("    },\n")
    f.write("""  };
}
""")

print(f"Generated {out_path} successfully!")
