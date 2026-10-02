import json
import os

additional = {
    # Section Headers
    "TAILORED SOLUTIONS": {
        "hi": "विशेष व्यावसायिक समाधान", "ar": "حلول مخصصة لنشاطك", "es": "SOLUCIONES A MEDIDA", "fr": "SOLUTIONS SUR MESURE",
        "pt": "SOLUÇÕES PERSONALIZADAS", "de": "MAßGESCHNEIDERTE LÖSUNGEN", "zh": "行业专属解决方案", "ja": "業種特化型ソリューション",
        "ru": "ОТРАСЛЕВЫЕ РЕШЕНИЯ", "it": "SOLUZIONI SU MISURA", "id": "SOLUSI KHUSUS INDUSTRI", "tr": "ÖZELLEŞTİRİLMİŞ ÇÖZÜMLER"
    },
    "Engineered for Every Business Vertical": {
        "hi": "हर व्यावसायिक क्षेत्र के लिए विशेष रूप से इंजीनियर", "ar": "مصممة باحترافية لتناسب جميع قطاعات الأعمال", "es": "Diseñado para cada sector empresarial",
        "fr": "Conçu pour tous les secteurs d'activité", "pt": "Projetado para todos os segmentos de negócios", "de": "Entwickelt für jede Branche",
        "zh": "为各大细分零售与服务业态深度定制", "ja": "あらゆる業界のビジネスモデルに適合", "ru": "Разработано для любых направлений бизнеса",
        "it": "Progettato per ogni settore commerciale", "id": "Dirancang untuk Berbagai Jenis Sektor Usaha", "tr": "Her İş Sektörü İçin Özel Olarak Geliştirildi"
    },
    "Specialized workflows that fit the exact operational model of your store.": {
        "hi": "विशेष कार्यप्रवाह जो आपके स्टोर के सटीक परिचालन मॉडल के अनुकूल हैं।", "ar": "سير عمل مخصص يتوافق بدقة مع طريقة تشغيل متجرك اليومية.", "es": "Flujos de trabajo que se adaptan con precisión al modelo operativo de tu tienda.",
        "fr": "Des flux de travail adaptés au modèle opérationnel exact de votre établissement.", "pt": "Fluxos de trabalho que atendem exatamente ao modelo da sua loja.", "de": "Spezialisierte Workflows, die exakt zu Ihren Abläufen passen.",
        "zh": "精准契合您门店日常运营模式的数字化工作流。", "ja": "店舗の運営オペレーションにぴったり適合する専用ワークフロー。", "ru": "Специализированные рабочие процессы, идеально подходящие под модель вашего магазина.",
        "it": "Flussi di lavoro che si adattano al modello operativo del tuo punto vendita.", "id": "Alur kerja khusus yang sesuai dengan model operasional toko Anda.", "tr": "Mağazanızın tam operasyonel modeline uyum sağlayan özel iş akışları."
    },
    "POWERFUL CAPABILITIES": {
        "hi": "शक्तिशाली क्षमताएं", "ar": "إمكانيات تقنية جبارة", "es": "CAPACIDADES POTENTES", "fr": "CAPACITÉS PUISSANTES",
        "pt": "RECURSOS PODEROSOS", "de": "LEISTUNGSSTARKE FUNKTIONEN", "zh": "卓越业务能力", "ja": "強力なシステム機能",
        "ru": "МОЩНЫЕ ВОЗМОЖНОСТИ", "it": "POTENTI FUNZIONALITÀ", "id": "KEMAMPUAN HANDAL", "tr": "GÜÇLÜ YETENEKLER"
    },
    "Everything You Need to Run & Scale": {
        "hi": "संचालन और विस्तार के लिए आवश्यक सब कुछ", "ar": "كل ما تحتاجه لإدارة وتوسيع نطاق أعمالك", "es": "Todo lo que necesitas para operar y escalar",
        "fr": "Tout ce dont vous avez besoin pour grandir", "pt": "Tudo o que você precisa para crescer e expandir", "de": "Alles für den erfolgreichen Betrieb und Wachstum",
        "zh": "满足企业日常运转与高速扩张所需", "ja": "ビジネスの運営と事業拡大に必要なすべて", "ru": "Всё для стабильной работы и масштабирования",
        "it": "Tutto il necessario per gestire e crescere", "id": "Segala yang Anda Butuhkan untuk Operasional & Skala Usaha", "tr": "İşletmenizi Büyütmek ve Yönetmek İçin Her Şey"
    },
    "Cutting-edge omnichannel sales, real-time inventory, and offline-first peace of mind.": {
        "hi": "अत्याधुनिक ओमनीचैनल बिक्री, रीयल-टाइम इन्वेंट्री और ऑफ़लाइन-प्रथम मानसिक शांति।", "ar": "مبيعات متعددة القنوات، ومخزون فوري، وأمان تام مع العمل بدون إنترنت.", "es": "Ventas omnicanal de vanguardia, inventario en tiempo real y fiabilidad sin conexión.",
        "fr": "Ventes omnicanales modernes, stocks en temps réel et fonctionnement hors ligne garanti.", "pt": "Vendas omnichannel modernas, estoque em tempo real e operação offline sem preocupações.", "de": "Moderne Omnichannel-Verkäufe, Echtzeit-Inventar und absolute Offline-Sicherheit.",
        "zh": "尖端全渠道销售、毫秒级实时库存管理与高可靠离线无网收银保障。", "ja": "最先端のオムニチャネル販売、リアルタイム在庫、オフライン稼働の安心感。", "ru": "Передовые омниканальные продажи, склад в реальном времени и надёжная офлайн-работа.",
        "it": "Vendite omnicanale avanzate, inventario in tempo reale e tranquillità della modalità offline.", "id": "Penjualan omnichannel mutakhir, inventaris waktu nyata, dan kenyamanan POS offline.", "tr": "Son teknoloji çok kanallı satışlar, gerçek zamanlı stok ve çevrimdışı çalışma güveni."
    },
    "TRANSPARENT PRICING": {
        "hi": "पारदर्शी मूल्य निर्धारण", "ar": "أسعار واضحة وشفافة", "es": "PRECIOS TRANSPARENTES", "fr": "TARIFS TRANSPARENTS",
        "pt": "PREÇOS TRANSPARENTES", "de": "TRANSPARENTE PREISE", "zh": "透明价格", "ja": "明瞭な料金",
        "ru": "ПРОЗРАЧНЫЕ ЦЕНЫ", "it": "PREZZI TRASPARENTI", "id": "HARGA TRANSPARAN", "tr": "ŞEFFAF FİYATLANDIRMA"
    },
    "Simple Plans for Businesses of Any Size": {
        "hi": "किसी भी आकार के व्यवसाय के लिए सरल योजनाएं", "ar": "خطط بسيطة ومرنة تناسب جميع أحجام الأعمال", "es": "Planes simples para negocios de cualquier tamaño",
        "fr": "Des offres adaptées à chaque taille d'entreprise", "pt": "Planos simples para empresas de qualquer porte", "de": "Einfache Tarife für Unternehmen jeder Größe",
        "zh": "适合各种规模企业的简单超值方案", "ja": "あらゆる規模のビジネスに対応したシンプルプラン", "ru": "Простые тарифы для бизнеса любого масштаба",
        "it": "Piani semplici per attività di qualsiasi dimensione", "id": "Paket Sederhana untuk Berbagai Skala Usaha", "tr": "Her Büyüklükteki İşletme İçin Basit Planlar"
    },
    "Zero hidden setup fees. Upgrade, downgrade, or cancel anytime.": {
        "hi": "शून्य छिपा हुआ सेटअप शुल्क। कभी भी अपग्रेड, डाउनग्रेड या रद्द करें।", "ar": "بدون أي رسوم خفية. يمكنك الترقية أو التخفيض أو الإلغاء في أي وقت.", "es": "Sin cargos de instalación ocultos. Cambia o cancela cuando quieras.",
        "fr": "Aucun frais caché. Modifiez ou résiliez votre abonnement à tout moment.", "pt": "Sem taxas ocultas. Faça upgrade, downgrade ou cancele a qualquer momento.", "de": "Keine versteckten Gebühren. Jederzeit flexibel anpassbar oder kündbar.",
        "zh": "绝无任何隐形初始安装费用。随时随地自主升级、降级或取消。", "ja": "初期費用や隠れたコストは一切不要。いつでもプラン変更・解約可能。", "ru": "Никаких скрытых платежей. Изменяйте тариф или отменяйте подписку в любой момент.",
        "it": "Nessun costo nascosto. Aggiorna, riduci o annulla in qualsiasi momento.", "id": "Tanpa biaya tersembunyi. Tingkatkan, turunkan, atau batalkan kapan saja.", "tr": "Gizli kurulum ücreti yok. İstediğiniz zaman yükseltin veya iptal edin."
    },
    "SOCIAL PROOF": {
        "hi": "ग्राहकों का विश्वास", "ar": "ثقة عملائنا حول العالم", "es": "CONFIANZA Y OPINIONES", "fr": "ILS NOUS FONT CONFIANCE",
        "pt": "DEPOIMENTOS DE CLIENTES", "de": "KUNDENSTIMMEN", "zh": "客户真实口碑", "ja": "導入実績と信頼",
        "ru": "ОТЗЫВЫ КЛИЕНТОВ", "it": "OPINIONI DEI CLIENTI", "id": "KEPERCAYAAN PELANGGAN", "tr": "MÜŞTERİ DENEYİMLERİ"
    },
    "Trusted by Leading Merchants Globally": {
        "hi": "दुनिया भर के प्रमुख व्यापारियों द्वारा विश्वसनीय", "ar": "موثوق به من قِبل آلاف المتاجر والشركات عالمياً", "es": "Elegido por comerciantes líderes en todo el mundo",
        "fr": "Plébiscité par des milliers de commerçants dans le monde", "pt": "Aprovado por grandes varejistas em todo o mundo", "de": "Weltweit geschätzt von führenden Händlern",
        "zh": "备受全球上万家零售与连锁商户信赖", "ja": "世界各国の有力加盟店から高い信頼を獲得", "ru": "Выбор ведущих торговых предприятий по всему миру",
        "it": "Scelto dai migliori commercianti a livello globale", "id": "Dipercaya oleh Ribuan Pebisnis Terkemuka di Seluruh Dunia", "tr": "Dünya Çapında Lider İşletmeler Tarafından Güveniliyor"
    },
    "Hear how store owners streamline their business daily.": {
        "hi": "जानें कि कैसे स्टोर मालिक रोजाना अपने व्यवसाय को सुगम बनाते हैं।", "ar": "تعرف على آراء أصحاب المتاجر وكيف طوروا أعمالهم معنا يومياً.", "es": "Descubre cómo los propietarios optimizan su operativa diaria.",
        "fr": "Découvrez comment nos clients simplifient leur gestion au quotidien.", "pt": "Veja como os lojistas simplificam sua rotina diária.", "de": "Erfahren Sie, wie Händler ihr Geschäft täglich effizienter führen.",
        "zh": "倾听门店经营者如何借助我们的系统轻松掌控日常业务。", "ja": "毎日の業務を効率化したオーナー様の生の声をご紹介。", "ru": "Узнайте, как владельцы магазинов оптимизируют свой бизнес каждый день.",
        "it": "Scopri come i commercianti ottimizzano la gestione quotidiana.", "id": "Simak bagaimana pemilik toko mempermudah operasional harian mereka.", "tr": "Mağaza sahiplerinin günlük işlerini nasıl kolaylaştırdığını keşfedin."
    },
    "QUESTIONS & ANSWERS": {
        "hi": "सवाल और जवाब", "ar": "أسئلة وإجابات", "es": "PREGUNTAS Y RESPUESTAS", "fr": "QUESTIONS & RÉPONSES",
        "pt": "PERGUNTAS E RESPOSTAS", "de": "FRAGEN & ANTWORTEN", "zh": "答疑解惑", "ja": "Q&A",
        "ru": "ВОПРОСЫ И ОТВЕТЫ", "it": "DOMANDE E RISPOSTE", "id": "TANYA JAWAB", "tr": "SORULAR VE CEVAPLAR"
    },
    "Have inquiries before starting? Find quick answers right here.": {
        "hi": "शुरू करने से पहले कोई पूछताछ है? त्वरित उत्तर यहाँ प्राप्त करें।", "ar": "هل لديك استفسار قبل البدء؟ إليك أهم الإجابات السريعة.", "es": "¿Tienes dudas antes de empezar? Encuentra respuestas rápidas aquí.",
        "fr": "Vous avez des questions avant de commencer ? Trouvez les réponses ici.", "pt": "Tem dúvidas antes de começar? Encontre respostas rápidas aqui.", "de": "Haben Sie noch Fragen? Hier finden Sie schnelle Antworten.",
        "zh": "在体验之前有疑问？即刻在此查阅详细解答。", "ja": "導入前に気になる点はありませんか？よくある回答をまとめました。", "ru": "Есть вопросы перед началом? Найдите ответы здесь.",
        "it": "Hai dubbi prima di iniziare? Trova subito le risposte.", "id": "Punya pertanyaan sebelum memulai? Temukan jawabannya di sini.", "tr": "Başlamadan önce sorularınız mı var? Hızlı yanıtları burada bulun."
    },
    "CONTACT & INQUIRIES": {
        "hi": "संपर्क और पूछताछ", "ar": "الاتصال والاستفسارات", "es": "CONTACTO Y CONSULTAS", "fr": "CONTACT ET DEMANDES",
        "pt": "CONTATO E DÚVIDAS", "de": "KONTAKT & ANFRAGEN", "zh": "联系咨询", "ja": "お問い合わせ・ご相談",
        "ru": "СВЯЗЬ И ЗАПРОСЫ", "it": "CONTATTI E RICHIESTE", "id": "KONTAK & PERTANYAAN", "tr": "İLETİŞİM VE TALEPLER"
    },
    "Connect with Our Sales & Support Team": {
        "hi": "हमारी बिक्री और सहायता टीम से जुड़ें", "ar": "تواصل مباشرة مع فريق المبيعات والدعم الفني", "es": "Conecta con nuestro equipo de ventas y soporte",
        "fr": "Échangez avec nos équipes commerciale et technique", "pt": "Fale com nossa equipe comercial e de suporte", "de": "Sprechen Sie mit unserem Vertriebs- und Support-Team",
        "zh": "与我们的销售和技术支持顾问取得联系", "ja": "営業・サポートチームへお気軽にご相談ください", "ru": "Свяжитесь с отделом продаж и клиентской поддержки",
        "it": "Mettiti in contatto con il team commerciale e di supporto", "id": "Hubungi Tim Penjualan & Dukungan Kami", "tr": "Satış ve Destek Ekibimizle İletişime Geçin"
    },
    "Have questions before signing up? Send us a message and our team will get in touch.": {
        "hi": "साइन अप करने से पहले कोई प्रश्न हैं? हमें एक संदेश भेजें और हमारी टीम आपसे संपर्क करेगी।", "ar": "هل لديك أسئلة قبل التسجيل؟ أرسل رسالتك وسيتواصل معك خبراؤنا قريباً.", "es": "¿Preguntas antes de registrarte? Envíanos un mensaje y te contactaremos.",
        "fr": "Des questions avant de vous inscrire ? Envoyez-nous un message pour être recontacté.", "pt": "Tem dúvidas antes de se cadastrar? Envie uma mensagem e entraremos em contato.", "de": "Fragen vor der Registrierung? Schreiben Sie uns, wir melden uns schnellstmöglich.",
        "zh": "注册前有任何疑问？发送留言，我们的顾问将在24小时内与您联系。", "ja": "ご登録前の疑問やご相談は、メッセージをお送りいただければ迅速にご案内します。", "ru": "Есть вопросы перед регистрацией? Отправьте сообщение, и мы свяжемся с вами.",
        "it": "Hai domande prima di registrarti? Inviaci un messaggio e ti risponderemo subito.", "id": "Ada pertanyaan sebelum mendaftar? Kirimkan pesan dan tim kami akan segera menghubungi Anda.", "tr": "Kayıt olmadan önce sorularınız mı var? Bize mesaj gönderin, ekibimiz sizinle iletişime geçsin."
    },

    # Hardware items
    "Thermal Printers": {
        "hi": "थर्मल प्रिंटर", "ar": "طابعات الإيصالات الحرارية", "es": "Impresoras Térmicas", "fr": "Imprimantes thermiques",
        "pt": "Impressoras Térmicas", "de": "Thermodrucker", "zh": "热敏小票打印机", "ja": "サーマルプリンター",
        "ru": "Термопринтеры", "it": "Stampanti Termiche", "id": "Printer Termal", "tr": "Termal Yazıcılar"
    },
    "58mm / 80mm ESC/POS via USB, Bluetooth & WiFi": {
        "hi": "58mm / 80mm ESC/POS यूएसबी, ब्लूटूथ और वाईफाई द्वारा", "ar": "طباعة 58 ملم / 80 ملم عبر USB وبلوتوث وWiFi", "es": "58mm / 80mm ESC/POS mediante USB, Bluetooth y WiFi",
        "fr": "58mm / 80mm ESC/POS via USB, Bluetooth et Wi-Fi", "pt": "58mm / 80mm ESC/POS via USB, Bluetooth e Wi-Fi", "de": "58mm / 80mm ESC/POS über USB, Bluetooth & WLAN",
        "zh": "支持USB、蓝牙和WiFi的58/80毫米ESC/POS打印机", "ja": "USB、Bluetooth、WiFi接続の58mm/80mm ESC/POS対応", "ru": "58мм / 80мм ESC/POS через USB, Bluetooth и Wi-Fi",
        "it": "58mm / 80mm ESC/POS tramite USB, Bluetooth e WiFi", "id": "58mm / 80mm ESC/POS melalui USB, Bluetooth & WiFi", "tr": "USB, Bluetooth ve WiFi ile 58mm / 80mm ESC/POS"
    },
    "Plug & Play": {
        "hi": "प्लग एंड प्ले", "ar": "تشغيل فوري مباشر", "es": "Plug & Play", "fr": "Prêt à l'emploi",
        "pt": "Plug & Play", "de": "Plug & Play", "zh": "即插即用", "ja": "プラグ＆プレイ",
        "ru": "Подключи и работай", "it": "Plug & Play", "id": "Plug & Play", "tr": "Tak ve Çalıştır"
    },
    "Barcode Scanners": {
        "hi": "बारकोड स्कैनर", "ar": "أجهزة قراءة الباركود", "es": "Lectores de Código de Barras", "fr": "Lecteurs code-barres",
        "pt": "Leitores de Código de Barras", "de": "Barcode-Scanner", "zh": "条码与二维码扫描枪", "ja": "バーコードリーダー",
        "ru": "Сканеры штрихкодов", "it": "Lettori di Codici a Barre", "id": "Pemindai Barcode", "tr": "Barkod Okuyucular"
    },
    "1D/2D QR handheld & fixed continuous scanners": {
        "hi": "1D/2D QR हैंडहेल्ड और फिक्स्ड निरंतर स्कैनर", "ar": "ماسحات 1D/2D وQR سريعة محمولة وثابتة", "es": "Escáneres de mano y fijos para códigos 1D/2D y QR",
        "fr": "Scanners 1D/2D et QR manuels et fixes en continu", "pt": "Scanners portáteis e fixos para 1D/2D e QR Code", "de": "1D/2D QR Hand- und Stand-Dauerscanner",
        "zh": "支持一维条码与二维QR码的手持及固定连续扫描器", "ja": "1D/2D/QR対応のハンディ＆定置式スキャナー", "ru": "Ручные и стационарные 1D/2D и QR-сканеры",
        "it": "Scanner manuali e fissi per codici 1D/2D e QR", "id": "Pemindai 1D/2D QR genggam dan stasioner", "tr": "1D/2D QR el tipi ve sabit sürekli okuyucular"
    },
    "Zero Setup": {
        "hi": "शून्य सेटअप", "ar": "بدون أي إعداد مسبق", "es": "Sin Configuración", "fr": "Zéro configuration",
        "pt": "Zero Configuração", "de": "Ohne Konfiguration", "zh": "零配置", "ja": "設定不要",
        "ru": "Без настройки", "it": "Zero Configurazioni", "id": "Tanpa Pengaturan", "tr": "Sıfır Kurulum"
    },
    "Cash Drawers": {
        "hi": "कैश ड्रावर (दराज़)", "ar": "أدراج النقدية الإلكترونية", "es": "Cajones Portamonedas", "fr": "Tiroirs-caisses",
        "pt": "Gavetas de Dinheiro", "de": "Geldkassette", "zh": "收银钱箱", "ja": "キャッシュドロワー",
        "ru": "Денежные ящики", "it": "Cassetti Contanti", "id": "Laci Uang Kasir", "tr": "Para Çekmeceleri"
    },
    "Auto-kick RJ11 electronic cash drawers": {
        "hi": "ऑटो-किक RJ11 इलेक्ट्रॉनिक कैश दराज़", "ar": "أدراج نقدية إلكترونية RJ11 تفتح آلياً مع كل عملية", "es": "Cajones electrónicos RJ11 de apertura automática",
        "fr": "Tiroirs-caisses électroniques RJ11 à ouverture automatique", "pt": "Gavetas eletrônicas RJ11 com abertura automática", "de": "Automatisch öffnende RJ11-Kassenladen",
        "zh": "支持RJ11接口的自动弹开式电子钱箱", "ja": "RJ11連動の自動開閉式キャッシュドロワー", "ru": "Электронные ящики RJ11 с автооткрытием",
        "it": "Cassetti contanti elettronici RJ11 con apertura automatica", "id": "Laci kasir elektronik RJ11 buka otomatis", "tr": "Otomatik açılan RJ11 elektronik para çekmeceleri"
    },
    "Instant Trigger": {
        "hi": "त्वरित ट्रिगर", "ar": "استجابة فورية", "es": "Activación Instantánea", "fr": "Déclenchement instantané",
        "pt": "Disparo Instantâneo", "de": "Sofort-Auslösung", "zh": "毫秒级弹出", "ja": "瞬時連動",
        "ru": "Мгновенный отклик", "it": "Apertura Istantanea", "id": "Pemicu Cepat", "tr": "Anında Tetikleme"
    },
    "Kitchen Displays": {
        "hi": "किचन डिस्प्ले", "ar": "شاشات عرض المطبخ KDS", "es": "Pantallas de Cocina (KDS)", "fr": "Écrans Cuisine (KDS)",
        "pt": "Telas de Cozinha (KDS)", "de": "Küchendisplays", "zh": "后厨KDS大屏", "ja": "キッチンディスプレイ",
        "ru": "Кухонные экраны KDS", "it": "Display da Cucina", "id": "Layar Dapur KDS", "tr": "Mutfak Ekranları (KDS)"
    },
    "Live KOT order routing & chef dispatch station": {
        "hi": "लाइव KOT ऑर्डर रूटिंग और शेफ डिस्पैच स्टेशन", "ar": "توجيه فوري لطلبات المطبخ KOT ومحطة تحضير الشيف", "es": "Enrutamiento de comandas KOT y estación de cocina en vivo",
        "fr": "Routage direct des bons de cuisine et écran chef", "pt": "Roteamento de pedidos KOT e estação de expedição do chef", "de": "Live KOT-Bestellweiterleitung & Küchenleitstand",
        "zh": "KOT菜品自动路由流转与主厨备餐看板", "ja": "調理伝票(KOT)のリアルタイム配信と配膳ステーション", "ru": "Маршрутизация заказов KOT и рабочий экран шеф-повара",
        "it": "Instradamento comande KOT in tempo reale e postazione chef", "id": "Penerusan pesanan KOT langsung & stasiun koki", "tr": "Canlı mutfak sipariş yönlendirme ve şef istasyonu"
    },
    "Live Sync": {
        "hi": "लाइव सिंक", "ar": "مزامنة لحظية", "es": "Sincronización en Vivo", "fr": "Synchronisation directe",
        "pt": "Sincronização em Tempo Real", "de": "Live-Synchronisation", "zh": "实时同步", "ja": "リアルタイム同期",
        "ru": "Живая синхронизация", "it": "Sincronizzazione Live", "id": "Sinkronisasi Langsung", "tr": "Canlı Eşitleme"
    },

    # Solutions
    "Retail & Supermarkets": {
        "hi": "खुदरा और सुपरमार्केट", "ar": "محلات التجزئة والسوبر ماركت", "es": "Comercio Minorista y Supermercados",
        "fr": "Commerce de détail & Supermarchés", "pt": "Varejo e Supermercados", "de": "Einzelhandel & Supermärkte",
        "zh": "零售专卖与生鲜商超", "ja": "小売店＆スーパーマーケット", "ru": "Розница и супермаркеты",
        "it": "Retail e Supermercati", "id": "Ritel & Supermarket", "tr": "Perakende ve Süpermarketler"
    },
    "High-speed barcode scanning, variant matrix, stock audits, customer loyalty, and multi-counter cash drawers.": {
        "hi": "हाई-स्पीड बारकोड स्कैनिंग, वेरिएंट मैट्रिक्स, स्टॉक ऑडिट, ग्राहक वफादारी, और मल्टी-काउंटर कैश दराज़।",
        "ar": "مسح باركود فائق السرعة، ومصفوفة المقاسات والألوان، وجرد المخزون، ونقاط الولاء، وأدراج نقدية متعددة.",
        "es": "Escaneo de alta velocidad, matriz de variantes, auditorías de stock, fidelización y múltiples cajas.",
        "fr": "Lecture rapide des codes-barres, gestion des déclinaisons, inventaires, fidélité et caisses multiples.",
        "pt": "Leitura rápida de código de barras, grade de produtos, auditorias de estoque e fidelização de clientes.",
        "de": "Hochgeschwindigkeits-Barcode-Scanning, Variantenmatrix, Bestandsprüfungen und Kundenbindung.",
        "zh": "极速条码扫描、多规格尺码颜色矩阵、实时盘点、会员积分体系及多收银台管理。",
        "ja": "高速バーコードスキャン、バリエーション管理、実地棚卸、ロイヤルティプログラム、複数レジ対応。",
        "ru": "Быстрое сканирование штрихкодов, матрица модификаций, инвентаризация, лояльность и работа нескольких касс.",
        "it": "Scansione rapida di codici a barre, matrice varianti, inventario, fidelizzazione e casse multiple.",
        "id": "Pemindaian barcode cepat, matriks varian, audit stok, loyalitas pelanggan, dan banyak kasir.",
        "tr": "Hızlı barkod tarama, varyant matrisi, stok sayımı, müşteri sadakati ve çoklu kasa çekmecesi."
    },
    "Restaurants & Cafés": {
        "hi": "रेस्तरां और कैफे", "ar": "المطاعم والمقاهي والكافيهات", "es": "Restaurantes y Cafeterías",
        "fr": "Restaurants et Cafés", "pt": "Restaurantes e Cafeterias", "de": "Restaurants & Cafés",
        "zh": "餐饮店与咖啡茶饮", "ja": "レストラン＆カフェ", "ru": "Рестораны и кафе",
        "it": "Ristoranti e Bar", "id": "Restoran & Kafe", "tr": "Restoranlar ve Kafeler"
    },
    "Interactive table floor plans, split bills, kitchen order tickets (KOT), recipe modifiers, and food delivery sync.": {
        "hi": "इंटरैक्टिव टेबल लेआउट, विभाजित बिल, किचन ऑर्डर टिकट (KOT), रेसिपी संशोधक, और ऑनलाइन डिलीवरी सिंक।",
        "ar": "مخطط طاولات تفاعلي، وتقسيم الفاتورة، وأوامر المطبخ KOT، وتعديل المكونات، ومزامنة توصيل الطلبات.",
        "es": "Plano interactivo de mesas, división de cuentas, comandas de cocina (KOT) y sincronización de envíos.",
        "fr": "Plan de salle interactif, partage d'addition, bons de commande cuisine (KOT) et livraison en ligne.",
        "pt": "Mapa de mesas interativo, divisão de contas, pedidos de cozinha (KOT) e integração de delivery.",
        "de": "Interaktiver Tischplan, getrennte Rechnungen, Küchen-Bons (KOT) und Lieferdienst-Abgleich.",
        "zh": "可视化交互式桌台地图、分单拆账、后厨KOT点菜单即刻打印与外卖订单实时同步。",
        "ja": "フロアテーブルマップ、割り勘会計、キッチン伝票(KOT)出力、レシピ調整、デリバリー連携。",
        "ru": "Интерактивная карта столов, раздельный чек, кухонные марки (KOT) и интеграция с доставкой.",
        "it": "Mappa interattiva dei tavoli, conti separati, comande cucina (KOT) e integrazione consegne.",
        "id": "Tata letak meja interaktif, pemisahan tagihan, tiket dapur KOT, dan sinkronisasi pesan antar.",
        "tr": "İnteraktif masa planı, adisyon bölme, mutfak sipariş fişleri (KOT) ve paket servis entegrasyonu."
    },
    "Pharmacies & Healthcare": {
        "hi": "फार्मेसी और स्वास्थ्य सेवा", "ar": "الصيدليات والمراكز الصحية", "es": "Farmacias y Salud",
        "fr": "Pharmacies et Santé", "pt": "Farmácias e Saúde", "de": "Apotheken & Gesundheitswesen",
        "zh": "药房药店与大健康", "ja": "薬局＆ヘルスケア", "ru": "Аптеки и здравоохранение",
        "it": "Farmacie e Salute", "id": "Farmasi & Kesehatan", "tr": "Eczaneler ve Sağlık"
    },
    "Batch number and expiry tracking, doctor prescription records, schedule drug audits, and automated stock reordering.": {
        "hi": "बैच नंबर और समाप्ति तिथि ट्रैकिंग, डॉक्टर के पर्चे का रिकॉर्ड, दवा ऑडिट, और स्वचालित स्टॉक पुनः ऑर्डर।",
        "ar": "تتبع أرقام التشغيلات وتواريخ انتهاء الصلاحية، وسجلات الوصفات الطبية، والطلب التلقائي للأدوية.",
        "es": "Seguimiento de lotes y caducidades, recetas médicas, auditorías de fármacos y reposición automática.",
        "fr": "Gestion des numéros de lot et péremption, ordonnances médicales et réapprovisionnement automatique.",
        "pt": "Rastreamento de lotes e validade, controle de receitas médicas e reposição automática de medicamentos.",
        "de": "Chargen- und Verfallsdatum-Tracking, Rezeptverwaltung und automatische Nachbestellungen.",
        "zh": "批号与效期追踪、医生处方管理、受管药品合规审查及安全库存智能自动补货。",
        "ja": "ロット番号・使用期限追跡、処方箋登録、医薬品管理、自動発注リオーダー。",
        "ru": "Учёт партий и сроков годности, рецептурные бланки, контроль фармпрепаратов и автозаказ.",
        "it": "Tracciamento lotti e scadenze, ricette mediche e riordino automatico scorte.",
        "id": "Pelacakan nomor batch dan kedaluwarsa, resep dokter, dan pemesanan ulang otomatis.",
        "tr": "Parti no ve son kullanma tarihi takibi, reçete kayıtları ve otomatik stok siparişi."
    },
    "Salons & Spas": {
        "hi": "सैलून और स्पा", "ar": "الصالونات ومراكز السبا والتجميل", "es": "Salones de Belleza y Spa",
        "fr": "Salons de coiffure et Spas", "pt": "Salões de Beleza e Spas", "de": "Salons & Spas",
        "zh": "美发沙龙与养生SPA", "ja": "サロン＆スパ施設", "ru": "Салоны красоты и СПА",
        "it": "Saloni e Spa", "id": "Salon & Spa", "tr": "Kuaför ve Spa Merkezleri"
    },
    "Staff commissions, appointment booking, chair management, package memberships, and SMS appointment reminders.": {
        "hi": "कर्मचारी कमीशन, अपॉइंटमेंट बुकिंग, कुर्सी प्रबंधन, पैकेज सदस्यता, और एसएमएस अनुस्मारक।",
        "ar": "عمولات الموظفين، وحجز المواعيد، وإدارة كراسي الخدمة، والاشتراكات، وتذكيرات الرسائل القصيرة.",
        "es": "Comisiones del personal, reserva de citas, gestión de puestos, membresías y recordatorios por SMS.",
        "fr": "Commissions des coiffeurs, prise de rendez-vous, gestion des cabines, forfaits et rappels SMS.",
        "pt": "Comissões de profissionais, agendamento de horários, pacotes de serviços e lembretes por SMS.",
        "de": "Mitarbeiterprovisionen, Terminbuchung, Platzverwaltung, Mitgliedschaften und SMS-Terminerinnerungen.",
        "zh": "技师绩效提成、在线预约挂号、工位排班、会员充值套卡与短信即时提醒。",
        "ja": "スタッフ歩合計算、予約管理、ブース管理、コース会員券、SMSリマインダー通知。",
        "ru": "Комиссионные мастеров, онлайн-запись, расписание кресел, абонементы и SMS-напоминания.",
        "it": "Provvigioni del personale, prenotazione appuntamenti, gestione postazioni, abbonamenti e promemoria SMS.",
        "id": "Komisi staf, pemesanan janji temu, manajemen kursi, paket keanggotaan, dan pengingat SMS.",
        "tr": "Personel primleri, randevu takvimi, koltuk yönetimi, paket üyelikler ve SMS hatırlatmaları."
    },

    # Features
    "Ultra-Fast Counter Checkout": {
        "hi": "अति-तेज़ काउंटर चेकआउट", "ar": "دفع سريع وفوري عند الكاونتر", "es": "Cobro ultrarrápido en caja",
        "fr": "Encaissement ultra-rapide en caisse", "pt": "Checkout ultrarrápido no balcão", "de": "Superschneller Kassiervorgang",
        "zh": "极致秒速收银结算", "ja": "超高速レジ会計", "ru": "Сверхбыстрый расчёт на кассе",
        "it": "Cassa ad altissima velocità", "id": "Pembayaran Kasir Sangat Cepat", "tr": "Ultra Hızlı Kasa Satışı"
    },
    "Complete sales in seconds with lightning-fast barcode lookup, split payments, cash change calculation, and instant receipt printing.": {
        "hi": "बिजली की गति से बारकोड लुकअप, स्प्लिट भुगतान, नकद परिवर्तन गणना, और त्वरित रसीद प्रिंटिंग के साथ सेकंडों में बिक्री पूरी करें।",
        "ar": "إتمام عمليات البيع في ثوانٍ مع بحث فوري عن الباركود، وتقسيم الدفع، وحساب المتبقي، وطباعة الإيصال فوراً.",
        "es": "Completa ventas en segundos con búsqueda veloz por código de barras, pagos divididos y emisión rápida de tiques.",
        "fr": "Encaissez en quelques secondes grâce à la recherche rapide, aux paiements fractionnés et à l'impression instantanée.",
        "pt": "Conclua vendas em segundos com busca rápida por código de barras, pagamentos divididos e impressão imediata de cupom.",
        "de": "Verkäufe in Sekundenschnelle abschließen mit Blitz-Barcodesuche, Teilzahlungen und Sofort-Belegdruck.",
        "zh": "毫秒级条码检索、多种组合拆分支付、自动计算找零并瞬间打印清晰小票，轻松应对高峰客流。",
        "ja": "電光石火のバーコード検索、分割支払い、自動お釣り計算、即時レシート印刷で数秒会計。",
        "ru": "Оформляйте продажу за секунды благодаря мгновенному поиску по штрихкоду, раздельной оплате и быстрой печати чеков.",
        "it": "Completa le vendite in pochi secondi con ricerca istantanea, pagamenti frazionati e stampa immediata dello scontrino.",
        "id": "Selesaikan transaksi dalam hitungan detik dengan pencarian barcode cepat, split payment, dan cetak struk instan.",
        "tr": "Yıldırım hızında barkod arama, parçalı ödeme, para üstü hesaplama ve anında fiş yazdırma ile saniyeler içinde satış yapın."
    },
    "100% Offline-First Architecture": {
        "hi": "100% ऑफ़लाइन-प्रथम आर्किटेक्चर", "ar": "بنية تقنية تعمل دون إنترنت بنسبة 100%", "es": "Arquitectura 100% Offline-First",
        "fr": "Architecture 100% hors ligne", "pt": "Arquitetura 100% Offline-First", "de": "100% Offline-First-Architektur",
        "zh": "100% 离线无网高可用架构", "ja": "100% オフラインファースト設計", "ru": "100% офлайн-архитектура",
        "it": "Architettura 100% Offline-First", "id": "Arsitektur 100% Offline-First", "tr": "%100 Çevrimdışı Çalışma Mimarisi"
    },
    "Never lose a sale during internet outages. Counters operate seamlessly offline with local caching and automatic background synchronization.": {
        "hi": "इंटरनेट बंद होने के दौरान भी कभी बिक्री न खोएं। स्थानीय कैशिंग और स्वचालित पृष्ठभूमि सिंक्रनाइज़ेशन के साथ काउंटर ऑफ़लाइन काम करते हैं।",
        "ar": "لن تفقد أي عملية بيع أبداً عند انقطاع الإنترنت. تواصل الكاونترات عملها دون انقطاع مع مزامنة خلفية تلقائية فور عودة الاتصال.",
        "es": "No pierdas ventas por cortes de internet. Las cajas siguen funcionando sin conexión con sincronización automática en segundo plano.",
        "fr": "Ne perdez aucune vente lors d'une panne réseau. Vos caisses fonctionnent hors ligne et se synchronisent automatiquement.",
        "pt": "Nunca perca uma venda por falta de internet. Os caixas continuam funcionando offline com sincronização automática.",
        "de": "Kein Verkaufsverlust bei Netzausfall. Die Kassen laufen offline stabil weiter und synchronisieren automatisch im Hintergrund.",
        "zh": "即使断网也绝不漏单。收银终端依托本地数据库无缝工作，网络恢复后在后台全自动智能双向同步。",
        "ja": "インターネットが切断されても売上機会を逃しません。ローカル保存により完全オフラインで動作し自動同期します。",
        "ru": "Никаких простоев при отключении интернета. Кассы продолжают работу локально, данные синхронизируются автоматически.",
        "it": "Non perdere vendite durante i cali di rete. I punti cassa operano offline sincronizzandosi automaticamente.",
        "id": "Jangan pernah kehilangan penjualan saat internet padam. Kasir tetap bekerja offline dengan sinkronisasi otomatis.",
        "tr": "İnternet kesintilerinde asla satış kaçırmayın. Kasalar yerel önbellek ile çevrimdışı çalışır ve arka planda otomatik eşitlenir."
    },
    "eCommerce Storefront & WhatsApp Orders": {
        "hi": "ईकॉमर्स स्टोरफ्रंट और व्हाट्सएप ऑर्डर", "ar": "متجر إلكتروني متكامل وطلبات واتساب", "es": "Tienda en línea y pedidos por WhatsApp",
        "fr": "Boutique en ligne & Commandes WhatsApp", "pt": "Loja Virtual e Pedidos via WhatsApp", "de": "eCommerce-Onlineshop & WhatsApp-Bestellungen",
        "zh": "在线云商城与WhatsApp一键点单", "ja": "オンラインショップ＆WhatsApp注文連携", "ru": "Интернет-магазин и заказы через WhatsApp",
        "it": "Store online e ordini via WhatsApp", "id": "Toko Online & Pesanan WhatsApp", "tr": "E-Ticaret Vitrini ve WhatsApp Siparişleri"
    },
    "Publish an interactive digital storefront in one click. Allow customers to browse inventory and send orders directly to your WhatsApp.": {
        "hi": "एक क्लिक में एक इंटरैक्टिव डिजिटल स्टोरफ्रंट प्रकाशित करें। ग्राहकों को इन्वेंट्री ब्राउज़ करने और सीधे आपके व्हाट्सएप पर ऑर्डर भेजने की अनुमति दें।",
        "ar": "أطلق متجرك الإلكتروني بنقرة واحدة. اسمح لعملائك بتصفح المنتجات وإرسال طلباتهم مباشرة إلى رقم الواتساب الخاص بك.",
        "es": "Publica una tienda digital interactiva en un clic. Permite que tus clientes vean el catálogo y envíen pedidos por WhatsApp.",
        "fr": "Publiez votre vitrine numérique en un clic. Vos clients consultent vos stocks et commandent directement via WhatsApp.",
        "pt": "Crie sua loja online com um clique. Deixe seus clientes navegarem no catálogo e enviarem pedidos pelo WhatsApp.",
        "de": "Erstellen Sie Ihren Onlineshop mit einem Klick. Kunden können Produkte ansehen und direkt per WhatsApp bestellen.",
        "zh": "一键上线专属独立网上微商城。顾客随时随地浏览实时库存，点选后将订单直接发送至您的官方WhatsApp。",
        "ja": "1クリックでオンラインカタログを公開。顧客が在庫を確認しWhatsApp経由で直接注文可能。",
        "ru": "Запустите онлайн-витрину в один клик. Клиенты смогут просматривать товары и отправлять заказы в ваш WhatsApp.",
        "it": "Pubblica la tua vetrina digitale in un clic. Consenti ai clienti di sfogliare il catalogo e ordinare su WhatsApp.",
        "id": "Publikasikan toko online interaktif dalam satu klik. Pelanggan dapat melihat katalog dan memesan via WhatsApp.",
        "tr": "Tek tıkla dijital mağaza vitrini yayınlayın. Müşterilerinizin kataloğu inceleyip doğrudan WhatsApp'ınıza sipariş iletmesini sağlayın."
    },
    "Live Real-Time Inventory Tracking": {
        "hi": "लाइव रीयल-टाइम इन्वेंट्री ट्रैकिंग", "ar": "تتبع فوري ومباشر لحركة المخزون", "es": "Control de Inventario en Tiempo Real",
        "fr": "Suivi des stocks en direct et en temps réel", "pt": "Controle de Estoque em Tempo Real", "de": "Echtzeit-Bestandsverfolgung",
        "zh": "多仓多店全时库存实时看板", "ja": "リアルタイム在庫トラッキング", "ru": "Контроль остатков в реальном времени",
        "it": "Tracciamento Inventario in Tempo Reale", "id": "Pelacakan Stok Barang Waktu Nyata", "tr": "Gerçek Zamanlı Stok Takibi"
    },
    "Track stock levels across all branches, receive low-stock alerts, manage inter-branch transfers, and audit discrepancies with ease.": {
        "hi": "सभी शाखाओं में स्टॉक स्तर ट्रैक करें, कम-स्टॉक अलर्ट प्राप्त करें, अंतर-शाखा स्थानान्तरण प्रबंधित करें, और विसंगतियों का ऑडिट करें।",
        "ar": "تابع كميات المنتجات في جميع الفروع، واستلم تنبيهات نقص المخزون، ونظم التحويلات بين الفروع واكشف الفروقات بكل سهولة.",
        "es": "Supervisa niveles de existencias en todas las sucursales, recibe alertas de stock bajo y gestiona transferencias con facilidad.",
        "fr": "Suivez les stocks de toutes vos boutiques, recevez des alertes de rupture et gérez les transferts facilement.",
        "pt": "Acompanhe o estoque em todas as filiais, receba alertas de reposição e controle transferências sem complicações.",
        "de": "Überwachen Sie Bestände über alle Filialen hinweg, erhalten Sie Warnungen bei geringem Vorrat und verwalten Sie Umlagerungen.",
        "zh": "统揽所有分店与中心仓库动态库存，自动接收补货预警，轻松处理调拨出入库及盘点盈亏对账。",
        "ja": "全店舗の在庫を一括監視し、欠品アラート受信、拠点間移動、棚卸差異の確認をスムーズに実行。",
        "ru": "Отслеживайте остатки во всех филиалах, получайте уведомления о заканчивающемся товаре и оформляйте перемещения.",
        "it": "Monitora i livelli di scorte in tutte le filiali, ricevi avvisi di sottoscorta e gestisci i trasferimenti facilmente.",
        "id": "Pantau jumlah stok di seluruh cabang, dapatkan peringatan stok menipis, dan kelola transfer barang dengan mudah.",
        "tr": "Tüm şubelerdeki stokları anlık izleyin, kritik stok uyarıları alın, şubeler arası transferleri zahmetsizce yönetin."
    },
    "Native Android & Windows Apps": {
        "hi": "नेटिव एंड्रॉइड और विंडोज ऐप्स", "ar": "تطبيقات أصلية لنظامي Android وWindows", "es": "Apps Nativas para Android y Windows",
        "fr": "Applications natives Android et Windows", "pt": "Aplicativos Nativos para Android e Windows", "de": "Native Apps für Android und Windows",
        "zh": "Android 与 Windows 原生桌面端应用", "ja": "Android・Windows ネイティブアプリ", "ru": "Нативные приложения для Android и Windows",
        "it": "App native per Android e Windows", "id": "Aplikasi Asli Android & Windows", "tr": "Yerel Android ve Windows Uygulamaları"
    },
    "Run on any device — counter PCs, Windows POS terminals, tablets, and Android handhelds with direct ESC/POS hardware support.": {
        "hi": "किसी भी डिवाइस पर चलाएं — काउंटर पीसी, विंडोज पीओएस टर्मिनल, टैबलेट और सीधे ईएससी/पीओएस समर्थन वाले एंड्रॉइड हैंडहेल्ड।",
        "ar": "يعمل على أي جهاز — أجهزة الكمبيوتر، ومحطات نقاط بيع Windows، والأجهزة اللوحية، وأجهزة Android المحمولة مع دعم مباشر للطابعات.",
        "es": "Ejecuta en cualquier equipo: PC de mostrador, terminales táctiles Windows, tablets y lectores móviles Android con soporte directo.",
        "fr": "Fonctionne sur tout appareil : PC de caisse, TPV Windows, tablettes et terminaux mobiles Android avec pilotes directs.",
        "pt": "Funcione em qualquer dispositivo: computadores, terminais Windows, tablets e celulares Android com suporte direto a periféricos.",
        "de": "Läuft auf jedem Gerät: Kassen-PCs, Windows-POS-Terminals, Tablets und Android-Handhelds mit nativer Hardwareanbindung.",
        "zh": "可直接在普通台式机、Windows收银一体机、平板电脑和Android智能手持终端上畅快运行，直驱各类硬件。",
        "ja": "カウンターPC、Windows POS端末、タブレット、Androidハンディ端末までESC/POS直接制御で完全対応。",
        "ru": "Работает на любых устройствах: ПК кассира, моноблоках Windows, планшетах и смартфонах Android с поддержкой термопринтеров.",
        "it": "Funziona su qualsiasi dispositivo: PC, terminali POS Windows, tablet e dispositivi mobili Android con supporto hardware.",
        "id": "Dapat dijalankan di perangkat apa pun: PC kasir, terminal Windows, tablet, dan smartphone Android dengan dukungan ESC/POS.",
        "tr": "Her cihazda çalışır: Kasa PC'leri, Windows POS terminalleri, tabletler ve el terminalleri ile doğrudan donanım desteği."
    },
    "Compliant Tax Invoices & Reports": {
        "hi": "अनुरूप टैक्स इनवॉइस और रिपोर्ट्स", "ar": "فواتير ضريبية وتقارير مالية معتمدة", "es": "Facturación e Informes Fiscales Legales",
        "fr": "Factures conformes et rapports fiscaux", "pt": "Notas Fiscais e Relatórios em Conformidade", "de": "Finanzamt-konforme Rechnungen & Berichte",
        "zh": "合规税务发票与多维财务报表", "ja": "各種税率対応の領収書・会計レポート", "ru": "Фискальные счета и финансовые отчёты",
        "it": "Fatture fiscali e report conformi", "id": "Faktur Pajak & Laporan Keuangan", "tr": "Mevzuata Uygun Fatura ve Raporlar"
    },
    "Generate GST/VAT compliant invoices, export daily Z-reports, view profit margins, and track cash drawer balances effortlessly.": {
        "hi": "जीएसटी/वैट अनुरूप इनवॉइस उत्पन्न करें, दैनिक जेड-रिपोर्ट निर्यात करें, लाभ मार्जिन देखें, और कैश ड्रावर बैलेंस ट्रैक करें।",
        "ar": "إصدار فواتير ضريبية معتمدة، وتصدير تقارير Z اليومية، ومتابعة هوامش الربح ورصيد صندوق النقدية بكل دقة.",
        "es": "Genera facturas conformes con IVA/GST, exporta cierres Z diarios, calcula márgenes y concilia el efectivo sin esfuerzo.",
        "fr": "Générez des factures conformes TVA, exportez vos rapports Z journaliers, analysez vos marges et suivez votre caisse.",
        "pt": "Gere faturas fiscais, exporte relatórios diários de fechamento, consulte margens de lucro e confira o caixa sem esforço.",
        "de": "Erstellen Sie steuerkonforme Rechnungen, exportieren Sie tägliche Z-Berichte und überwachen Sie Kassenstände und Gewinnspannen.",
        "zh": "支持增值税开票要求，一键导出日结Z报表，直观查看商品毛利率分析并清晰对账收银机钱箱现金结余。",
        "ja": "インボイス対応請求書の発行、日次Zレポ出力、利益率分析、レジ内の現金管理をスムーズに実現。",
        "ru": "Формируйте счета с НДС, выгружайте Z-отчёты за смену, анализируйте маржинальность и контролируйте кассу без лишних усилий.",
        "it": "Genera fatture conformi, esporta chiusure Z giornaliere, monitora i margini di profitto e controlla il fondo cassa.",
        "id": "Buat faktur pajak resmi, ekspor laporan harian Z, lihat margin keuntungan, dan lacak saldo kasir dengan mudah.",
        "tr": "KDV uyumlu faturalar oluşturun, günlük Z raporları alın, kâr marjlarını görüntüleyin ve kasa bakiyelerini kolayca takip edin."
    },

    # Stats
    "Happy Businesses": {
        "hi": "संतुष्ट व्यवसाय", "ar": "أعمال تجارية ناجحة", "es": "Negocios Satisfechos", "fr": "Entreprises clientes",
        "pt": "Empresas Atendidas", "de": "Zufriedene Händler", "zh": "活跃商户企业", "ja": "導入店舗数",
        "ru": "Довольных клиентов", "it": "Attività Soddisfatte", "id": "Bisnis yang Puas", "tr": "Mutlu İşletmeler"
    },
    "Customer Rating": {
        "hi": "ग्राहक रेटिंग", "ar": "تقييم العملاء", "es": "Valoración de Clientes", "fr": "Note des clients",
        "pt": "Avaliação dos Clientes", "de": "Kundenbewertung", "zh": "商户综合好评率", "ja": "顧客満足度スコア",
        "ru": "Оценка клиентов", "it": "Valutazione Clienti", "id": "Penilaian Pelanggan", "tr": "Müşteri Puanı"
    },
    "Uptime Guarantee": {
        "hi": "अपटाइम गारंटी", "ar": "ضمان تشغيل النظام", "es": "Garantía de Disponibilidad", "fr": "Disponibilité garantie",
        "pt": "Garantia de Uptime", "de": "Verfügbarkeitsgarantie", "zh": "云端运行高可用保证", "ja": "稼働率保証(SLA)",
        "ru": "Гарантия доступности", "it": "Garanzia di Uptime", "id": "Jaminan Waktu Aktif", "tr": "Çalışma Süresi Garantisi"
    },
    "Customer Support": {
        "hi": "ग्राहक सहायता", "ar": "الدعم الفني والخدمة", "es": "Atención al Cliente", "fr": "Support client dédié",
        "pt": "Suporte ao Cliente", "de": "Kundenservice", "zh": "专业客户支持", "ja": "カスタマーサポート",
        "ru": "Поддержка клиентов", "it": "Assistenza Clienti", "id": "Dukungan Pelanggan", "tr": "Müşteri Desteği"
    },

    # Pricing details
    "Essential tools for single-counter stores": {
        "hi": "एकल-काउंटर स्टोर के लिए आवश्यक उपकरण", "ar": "الأدوات الأساسية للمتاجر ذات الكاونتر الواحد", "es": "Herramientas esenciales para tiendas de una caja",
        "fr": "Les outils essentiels pour les commerces à caisse unique", "pt": "Ferramentas essenciais para lojas de caixa único", "de": "Grundlegende Werkzeuge für Ein-Kassen-Geschäfte",
        "zh": "单收银台独立小店的必备数字化工具", "ja": "単一レジ店舗に必要な必須基本ツール", "ru": "Базовые инструменты для магазинов с одной кассой",
        "it": "Strumenti essenziali per negozi con una sola cassa", "id": "Alat penting untuk toko dengan satu meja kasir", "tr": "Tek kasalı mağazalar için temel araçlar"
    },
    "Best for growing multi-counter retail & restaurants": {
        "hi": "बढ़ते मल्टी-काउंटर रिटेल और रेस्तरां के लिए सर्वश्रेष्ठ", "ar": "الأفضل للمتاجر والمطاعم ذات نقاط البيع المتعددة", "es": "Ideal para tiendas y restaurantes en expansión",
        "fr": "Idéal pour les commerces et restaurants multi-caisses", "pt": "Ideal para lojas e restaurantes com múltiplos caixas", "de": "Perfekt für wachsende Betriebe mit mehreren Kassen",
        "zh": "适合稳步成长中的多柜台零售商户与连锁餐厅", "ja": "複数レジを運用する成長中の店舗・飲食店に最適", "ru": "Идеально для растущих магазинов и ресторанов с несколькими кассами",
        "it": "Ideale per negozi e ristoranti con più casse in crescita", "id": "Terbaik untuk ritel & restoran dengan banyak meja kasir", "tr": "Büyüyen çok kasalı perakende ve restoranlar için en iyisi"
    },
    "Full custom deployment with white-label branding": {
        "hi": "व्हाइट-लेबल ब्रांडिंग के साथ पूर्ण कस्टम परिनियोजन", "ar": "نشر مخصص بالكامل مع تخصيص هوية العلامة التجارية", "es": "Implementación a medida con marca blanca",
        "fr": "Déploiement sur mesure avec personnalisation en marque blanche", "pt": "Implantação personalizada com marca própria", "de": "Individuelle Bereitstellung mit eigenem Branding",
        "zh": "支持独立私有化部署与品牌贴牌定制", "ja": "ホワイトラベル対応のフルカスタム導入プラン", "ru": "Индивидуальное развёртывание с собственным брендингом",
        "it": "Implementazione personalizzata con white-label", "id": "Penerapan kustom lengkap dengan merek sendiri", "tr": "Beyaz etiketli markalama ile tam özel kurulum"
    },
    "1 Store Location": {
        "hi": "1 स्टोर स्थान", "ar": "فرع متجر واحد", "es": "1 Sucursal", "fr": "1 Magasin",
        "pt": "1 Localização de Loja", "de": "1 Filialstandort", "zh": "1家实体门店", "ja": "1店舗ライセンス",
        "ru": "1 торговая точка", "it": "1 Punto Vendita", "id": "1 Lokasi Toko", "tr": "1 Mağaza Konumu"
    },
    "Up to 1,000 Products": {
        "hi": "1,000 उत्पादों तक", "ar": "حتى 1000 منتج", "es": "Hasta 1.000 productos", "fr": "Jusqu'à 1 000 produits",
        "pt": "Até 1.000 produtos", "de": "Bis zu 1.000 Artikel", "zh": "最高可录入1,000种商品", "ja": "最大1,000品目の商品登録",
        "ru": "До 1 000 товаров", "it": "Fino a 1.000 prodotti", "id": "Hingga 1.000 Produk", "tr": "1.000 Ürüne Kadar"
    },
    "Offline-Ready POS": {
        "hi": "ऑफ़लाइन-तैयार पीओएस", "ar": "نقطة بيع تدعم العمل بدون إنترنت", "es": "TPV preparado para offline", "fr": "Caisse prête pour le hors-ligne",
        "pt": "PDV pronto para funcionar offline", "de": "Offline-fähiges Kassensystem", "zh": "全功能离线收银终端", "ja": "オフライン対応POSレジ",
        "ru": "Касса с поддержкой офлайн", "it": "POS pronto per l'offline", "id": "POS Siap Pakai Offline", "tr": "Çevrimdışına Hazır POS"
    },
    "Receipt Printing": {
        "hi": "रसीद प्रिंटिंग", "ar": "طباعة الإيصالات", "es": "Impresión de Tiques", "fr": "Impression de tickets",
        "pt": "Impressão de Recibos", "de": "Belegdruck", "zh": "小票打印与电子票据", "ja": "レシート印刷機能",
        "ru": "Печать чеков", "it": "Stampa Scontrini", "id": "Pencetakan Struk", "tr": "Fiş Yazdırma"
    },
    "Standard Email Support": {
        "hi": "मानक ईमेल सहायता", "ar": "دعم فني قياسي عبر البريد", "es": "Soporte estándar por email", "fr": "Assistance standard par e-mail",
        "pt": "Suporte padrão por e-mail", "de": "Standard-E-Mail-Support", "zh": "标准电子邮箱客服支持", "ja": "標準メールサポート",
        "ru": "Стандартная поддержка по email", "it": "Supporto standard via email", "id": "Dukungan Email Standar", "tr": "Standart E-posta Desteği"
    },
    "Multi-Location Support": {
        "hi": "मल्टी-स्थान समर्थन", "ar": "دعم فروع متعددة", "es": "Soporte multi-sucursal", "fr": "Gestion multi-établissements",
        "pt": "Suporte a múltiplas filiais", "de": "Unterstützung mehrerer Filialen", "zh": "多门店连锁分店支持", "ja": "複数店舗の統合管理対応",
        "ru": "Поддержка нескольких филиалов", "it": "Supporto multi-sede", "id": "Dukungan Multi-Cabang", "tr": "Çoklu Şube Desteği"
    },
    "Unlimited Products & Invoices": {
        "hi": "असीमित उत्पाद और इनवॉइस", "ar": "منتجات وفواتير غير محدودة", "es": "Productos y facturas ilimitados", "fr": "Produits et factures illimités",
        "pt": "Produtos e faturas ilimitadas", "de": "Unbegrenzte Artikel & Rechnungen", "zh": "无上限商品种类与开票单据", "ja": "商品点数・請求書発行 無制限",
        "ru": "Безлимитные товары и счета", "it": "Prodotti e fatture illimitati", "id": "Produk & Faktur Tanpa Batas", "tr": "Sınırsız Ürün ve Fatura"
    },
    "eCommerce Digital Storefront": {
        "hi": "ईकॉमर्स डिजिटल स्टोरफ्रंट", "ar": "واجهة متجر إلكتروني رقمية", "es": "Tienda digital online", "fr": "Boutique e-commerce intégrée",
        "pt": "Loja digital para vendas online", "de": "Integrierter Online-Store", "zh": "配套线上独立微商城", "ja": "デジタルオンラインストアフロント",
        "ru": "Цифровая интернет-витрина", "it": "Vetrina digitale online", "id": "Etalase Toko Digital", "tr": "Dijital E-Ticaret Vitrini"
    },
    "Table Floor Management & KOT": {
        "hi": "टेबल फ्लोर प्रबंधन और KOT", "ar": "إدارة الطاولات وأوامر المطبخ KOT", "es": "Gestión de mesas y comandas KOT", "fr": "Plan de salle et bons cuisine KOT",
        "pt": "Gestão de mesas e comandas KOT", "de": "Tischmanagement & Küchen-Bons", "zh": "桌台楼层地图与后厨KOT系统", "ja": "テーブル管理＆厨房伝票(KOT)",
        "ru": "Управление залом столов и марками KOT", "it": "Gestione tavoli e comande KOT", "id": "Manajemen Meja & Tiket Dapur", "tr": "Masa Yönetimi ve Mutfak Fişleri"
    },
    "Staff Permissions & Audit Logs": {
        "hi": "कर्मचारी अनुमतियाँ और ऑडिट लॉग", "ar": "صلاحيات الموظفين وسجلات العمليات", "es": "Permisos de empleados y registro de actividad", "fr": "Droits d'accès et historique des audits",
        "pt": "Permissões de equipe e histórico de ações", "de": "Mitarbeiterrechte & Überwachungsprotokolle", "zh": "员工岗位角色权限与全流程审计日志", "ja": "スタッフ権限設定＆操作履歴ログ",
        "ru": "Права доступа сотрудников и аудит-логи", "it": "Permessi del personale e registro verifiche", "id": "Hak Akses Staf & Log Audit", "tr": "Personel Yetkileri ve Denetim Kayıtları"
    },
    "Priority WhatsApp Support": {
        "hi": "प्राथमिकता व्हाट्सएप सहायता", "ar": "دعم ذو أولوية عبر الواتساب", "es": "Soporte prioritario por WhatsApp", "fr": "Assistance prioritaire par WhatsApp",
        "pt": "Suporte prioritário via WhatsApp", "de": "Prioritäts-Support über WhatsApp", "zh": "专属VIP快速即时通讯支持", "ja": "優先WhatsAppサポート",
        "ru": "Приоритетная поддержка в WhatsApp", "it": "Supporto prioritario via WhatsApp", "id": "Dukungan Prioritas WhatsApp", "tr": "Öncelikli WhatsApp Desteği"
    },
    "Unlimited Branches & Warehouses": {
        "hi": "असीमित शाखाएं और गोदाम", "ar": "فروع ومستودعات تخزين غير محدودة", "es": "Sucursales y almacenes ilimitados", "fr": "Filiales et entrepôts illimités",
        "pt": "Filiais e armazéns ilimitados", "de": "Unbegrenzte Filialen & Lager", "zh": "不限数量的跨区分店与总分仓库", "ja": "店舗数・倉庫数 無制限",
        "ru": "Неограниченно числа филиалов и складов", "it": "Filiali e magazzini illimitati", "id": "Cabang & Gudang Tanpa Batas", "tr": "Sınırsız Şube ve Depo"
    },
    "Dedicated Database & Domain": {
        "hi": "समर्पित डेटाबेस और डोमेन", "ar": "قاعدة بيانات ونطاق مخصص خاص بك", "es": "Base de datos y dominio dedicados", "fr": "Base de données et domaine dédiés",
        "pt": "Banco de dados e domínio dedicados", "de": "Eigene Datenbank & eigene Domain", "zh": "独立云数据库与自定义专属主域名", "ja": "専用データベース＆独自ドメイン",
        "ru": "Выделенная база данных и домен", "it": "Database e dominio dedicati", "id": "Database & Domain Khusus", "tr": "Özel Veritabanı ve Özel Alan Adı"
    },
    "White-Label Custom Branding": {
        "hi": "व्हाइट-लेबल कस्टम ब्रांडिंग", "ar": "تخصيص الهوية والشعار بالكامل", "es": "Marca blanca y personalización completa", "fr": "Marque blanche et personnalisation totale",
        "pt": "Marca própria com personalização completa", "de": "White-Label mit individuellem Branding", "zh": "纯净白标独立自有品牌包装", "ja": "ホワイトラベル自社ブランド対応",
        "ru": "White-label и полный кастомный брендинг", "it": "Personalizzazione completa con White-Label", "id": "Merek Kustom White-Label", "tr": "Beyaz Etiketli Özel Markalama"
    },
    "Automated Cloud Backups": {
        "hi": "स्वचालित क्लाउड बैकअप", "ar": "نسخ احتياطي سحابي تلقائي مشفر", "es": "Copias de seguridad automáticas en la nube", "fr": "Sauvegardes automatiques dans le cloud",
        "pt": "Backups automáticos na nuvem", "de": "Automatische Cloud-Sicherungen", "zh": "异地双活云端自动定时快照备份", "ja": "自動クラウドバックアップ",
        "ru": "Автоматическое резервное копирование в облако", "it": "Backup automatici nel cloud", "id": "Cadangan Cloud Otomatis", "tr": "Otomatik Bulut Yedeklemeleri"
    },
    "Dedicated Account Manager": {
        "hi": "समर्पित खाता प्रबंधक", "ar": "مدير حساب مخصص لخدمتكم", "es": "Gestor de cuenta dedicado", "fr": "Responsable de compte dédié",
        "pt": "Gerente de conta exclusivo", "de": "Persönlicher Kundenbetreuer", "zh": "1对1大客户专属客户成功经理", "ja": "専任アカウントマネージャー",
        "ru": "Персональный менеджер аккаунта", "it": "Account manager dedicato", "id": "Manajer Akun Khusus", "tr": "Özel Müşteri Temsilcisi"
    },
    "Unlimited Invoices": {
        "hi": "असीमित इनवॉइस", "ar": "فواتير غير محدودة", "es": "Facturas Ilimitadas", "fr": "Factures illimitées",
        "pt": "Faturas Ilimitadas", "de": "Unbegrenzte Rechnungen", "zh": "不限单据数量", "ja": "無制限の請求書",
        "ru": "Безлимитные счета", "it": "Fatture Illimitate", "id": "Faktur Tanpa Batas", "tr": "Sınırsız Fatura"
    },
    "Unlimited Products": {
        "hi": "असीमित उत्पाद", "ar": "منتجات غير محدودة", "es": "Productos Ilimitados", "fr": "Produits illimités",
        "pt": "Produtos Ilimitados", "de": "Unbegrenzte Artikel", "zh": "不限商品总数", "ja": "無制限の商品登録",
        "ru": "Безлимитные товары", "it": "Prodotti Illimitati", "id": "Produk Tanpa Batas", "tr": "Sınırsız Ürün"
    },
    "Unlimited POS Devices": {
        "hi": "असीमित पीओएस डिवाइस", "ar": "أجهزة كاشير غير محدودة", "es": "Dispositivos TPV Ilimitados", "fr": "Terminaux POS illimités",
        "pt": "Dispositivos de PDV Ilimitados", "de": "Unbegrenzte POS-Geräte", "zh": "不限收银终端台数", "ja": "無制限のPOS端末",
        "ru": "Неограниченно кассовых устройств", "it": "Dispositivi POS Illimitati", "id": "Perangkat Kasir Tanpa Batas", "tr": "Sınırsız POS Cihazı"
    },
    "Unlimited Staff": {
        "hi": "असीमित कर्मचारी", "ar": "موظفون غير محدودين", "es": "Personal Ilimitado", "fr": "Personnel illimité",
        "pt": "Colaboradores Ilimitados", "de": "Unbegrenzte Mitarbeiter", "zh": "不限员工账号数", "ja": "無制限のスタッフ登録",
        "ru": "Неограниченно сотрудников", "it": "Personale Illimitato", "id": "Karyawan Tanpa Batas", "tr": "Sınırsız Personel"
    },
    "CRM & Leads": {
        "hi": "सीआरएम और लीड्स", "ar": "إدارة العملاء والفرص (CRM)", "es": "CRM y Clientes Potenciales", "fr": "CRM et Prospects",
        "pt": "CRM e Oportunidades", "de": "CRM & Leads", "zh": "CRM客户管理与商机线索", "ja": "CRM・見込み客管理",
        "ru": "CRM и потенциальные клиенты", "it": "CRM e Contatti", "id": "CRM & Calon Pelanggan", "tr": "CRM ve Müşteri Adayları"
    },
    "WhatsApp API": {
        "hi": "व्हाट्सएप एपीआई", "ar": "واجهة واتساب API", "es": "API de WhatsApp", "fr": "API WhatsApp",
        "pt": "API do WhatsApp", "de": "WhatsApp API", "zh": "WhatsApp 官方集成接口", "ja": "WhatsApp API連携",
        "ru": "API WhatsApp", "it": "API WhatsApp", "id": "WhatsApp API", "tr": "WhatsApp API"
    },
    "Custom Domain": {
        "hi": "कस्टम डोमेन", "ar": "نطاق خاص مخصص", "es": "Dominio Personalizado", "fr": "Domaine personnalisé",
        "pt": "Domínio Personalizado", "de": "Eigene Domain", "zh": "独立绑定个性域名", "ja": "独自カスタムドメイン",
        "ru": "Собственный домен", "it": "Dominio Personalizzato", "id": "Domain Kustom", "tr": "Özel Alan Adı"
    },
    "MOST POPULAR": {
        "hi": "सर्वाधिक लोकप्रिय", "ar": "الأكثر طلباً", "es": "MÁS POPULAR", "fr": "LE PLUS POPULAIRE",
        "pt": "MAIS POPULAR", "de": "AM BELIEBTESTEN", "zh": "最受欢迎", "ja": "一番人気",
        "ru": "САМЫЙ ПОПУЛЯРНЫЙ", "it": "PIÙ POPOLARE", "id": "PALING POPULER", "tr": "EN POPÜLER"
    },

    # FAQs
    "Does the POS continue working when the internet drops?": {
        "hi": "क्या इंटरनेट बंद होने पर भी पीओएस काम करता रहता है?",
        "ar": "هل تواصل نقطة البيع العمل في حال انقطاع الإنترنت؟",
        "es": "¿Sigue funcionando el TPV si se corta el internet?",
        "fr": "La caisse continue-t-elle de fonctionner sans connexion internet ?",
        "pt": "O PDV continua funcionando quando a internet cai?",
        "de": "Funktioniert das Kassensystem auch bei Internetausfall weiter?",
        "zh": "当网络中断时，收银机系统还能正常进行销售结账吗？",
        "ja": "インターネットが切断された場合でもPOSレジは継続利用できますか？",
        "ru": "Продолжает ли работать касса при отключении интернета?",
        "it": "Il POS continua a funzionare se la connessione internet si interrompe?",
        "id": "Apakah POS tetap berfungsi saat koneksi internet terputus?",
        "tr": "İnternet kesildiğinde POS çalışmaya devam eder mi?"
    },
    "Yes, 100%. Product lookup, barcode scanning, cart calculations, and checkout continue running locally on your device without pause. When internet connection returns, offline sales synchronize automatically in the background with zero data loss.": {
        "hi": "हाँ, 100%। उत्पाद खोज, बारकोड स्कैनिंग, कार्ट गणना और चेकआउट बिना किसी रुकावट के आपके डिवाइस पर स्थानीय रूप से चलते रहते हैं। जब इंटरनेट वापस आता है, तो ऑफ़लाइन बिक्री शून्य डेटा हानि के साथ पृष्ठभूमि में स्वचालित रूप से सिंक्रनाइज़ हो जाती है।",
        "ar": "نعم، بنسبة 100%. يستمر البحث عن المنتجات ومسح الباركود وحساب السلة وإتمام العمليات محلياً على جهازك دون أي توقف. وعبر استعادة الاتصال، تتم مزامنة المبيعات تلقائياً دون أي فقدان للبيانات.",
        "es": "Sí, al 100%. La búsqueda de artículos, el escaneo de códigos, los totales y el cobro siguen funcionando localmente. Al restablecerse la red, las ventas se sincronizan solas sin pérdida de datos.",
        "fr": "Oui, à 100%. La recherche d'articles, la lecture des codes-barres, le calcul et l'encaissement continuent localement. Dès le retour de la connexion, les ventes se synchronisent en arrière-plan sans aucune perte.",
        "pt": "Sim, 100%. Busca de produtos, leitura de código de barras e pagamentos continuam funcionando localmente no dispositivo. Ao restabelecer a conexão, tudo é sincronizado sem perda de dados.",
        "de": "Ja, zu 100%. Artikelsuche, Barcode-Scanning, Warenkorb und Kassiervorgang laufen lokal weiter. Sobald das Internet zurückkehrt, synchronisieren sich alle Verkäufe im Hintergrund verlustfrei.",
        "zh": "是的，百分之百高可用。商品智能检索、条码扫描、购物车结算及钱箱打印均在本地秒级运行。一旦网络重连，所有离线单据自动在后台安全上报，数据零丢失。",
        "ja": "はい、100%安心です。商品検索、バーコード読取、会計処理はすべて端末ローカルで問題なく稼働します。ネット復帰時にバックグラウンドで自動同期されデータ損失はありません。",
        "ru": "Да, на 100%. Поиск товаров, сканирование штрихкодов и расчёт покупателей продолжают работать локально. При появлении сети данные автоматически синхронизируются без потерь.",
        "it": "Sì, al 100%. Ricerca prodotti, scansione codici a barre e pagamenti continuano in locale. Quando la rete si ripristina, le vendite si sincronizzano automaticamente in background.",
        "id": "Ya, 100%. Pencarian produk, pemindaian barcode, dan pembayaran tetap berjalan secara lokal di perangkat. Saat internet kembali, transaksi disinkronkan otomatis.",
        "tr": "Evet, %100. Ürün arama, barkod tarama, sepet tutarı ve ödeme işlemleri cihazınızda yerel olarak kesintisiz çalışır. İnternet geldiğinde tüm satışlar veri kaybı olmadan arka planda eşitlenir."
    },
    "Which hardware devices and printers are supported?": {
        "hi": "कौन से हार्डवेयर डिवाइस और प्रिंटर समर्थित हैं?",
        "ar": "ما هي الأجهزة والطابعات المدعومة؟",
        "es": "¿Qué dispositivos y periféricos de hardware son compatibles?",
        "fr": "Quels périphériques et imprimantes sont pris en charge ?",
        "pt": "Quais dispositivos e impressoras são suportados?",
        "de": "Welche Hardware-Geräte und Drucker werden unterstützt?",
        "zh": "系统支持哪些收银硬件设备与热敏打印机？",
        "ja": "どのようなハードウェア機器やプリンターに対応していますか？",
        "ru": "Какое оборудование и принтеры поддерживаются?",
        "it": "Quali dispositivi hardware e stampanti sono supportati?",
        "id": "Perangkat keras dan printer apa saja yang didukung?",
        "tr": "Hangi donanım cihazları ve yazıcılar destekleniyor?"
    },
    "Any standard USB or Bluetooth barcode scanner, 80mm and 58mm thermal receipt printers, auto-kick cash drawers, EMV/NFC card terminals, and kitchen display monitors. If it connects to Windows, Android, or browser, it works out of the box.": {
        "hi": "कोई भी मानक यूएसबी या ब्लूटूथ बारकोड स्कैनर, 80 मिमी और 58 मिमी थर्मल रसीद प्रिंटर, ऑटो-किक कैश दराज़, ईएमवी/एनएफसी कार्ड टर्मिनल, और रसोई डिस्प्ले मॉनिटर। यदि यह विंडोज, एंड्रॉइड या ब्राउज़र से जुड़ता है, तो यह तुरंत काम करता है।",
        "ar": "أي ماسح ضوئي قياسي للباركود يعمل عبر USB أو البلوتوث، وطابعات الإيصالات الحرارية 80 ملم و58 ملم، وأدراج النقدية، وأجهزة نقاط البيع، وشاشات المطبخ. طالما يتصل بنظام Windows أو Android أو المتصفح، سيعمل مباشرة.",
        "es": "Cualquier lector USB o Bluetooth, impresoras térmicas de 58mm y 80mm, cajones portamonedas, terminales de cobro y pantallas de cocina. Si se conecta a Windows, Android o navegador, funciona de inmediato.",
        "fr": "Tout scanner de code-barres USB ou Bluetooth, imprimantes thermiques 58mm et 80mm, tiroirs-caisses, terminaux de paiement et écrans cuisine. S'il se connecte à Windows, Android ou au navigateur, c'est compatible.",
        "pt": "Qualquer leitor de código de barras USB ou Bluetooth, impressoras térmicas de 58mm e 80mm, gavetas de dinheiro e monitores de cozinha. Se conecta ao Windows, Android ou navegador, funciona na hora.",
        "de": "Jeder Standard-USB- oder Bluetooth-Scanner, 58mm & 80mm Thermodrucker, Kassenladen und Küchenmonitore. Läuft direkt unter Windows, Android oder im Webbrowser.",
        "zh": "支持任意市售通用USB或蓝牙扫码枪、58mm与80mm热敏收银小票机、自动钱箱、刷卡机以及后厨显示器。只要能连接到Windows、Android或现代浏览器，即可即插即用。",
        "ja": "一般的なUSB/Bluetoothバーコードスキャナー、58mm/80mmレシートプリンター、キャッシュドロワー、キッチン用モニターなど、WindowsやAndroidに接続できればそのまま使用可能です。",
        "ru": "Любые стандартные USB или Bluetooth сканеры, термопринтеры 58/80 мм, денежные ящики и кухонные дисплеи. Если подключается к Windows, Android или браузеру — работает из коробки.",
        "it": "Qualsiasi scanner barcode USB o Bluetooth, stampanti termiche 58mm e 80mm, cassetti contanti e schermi cucina. Se si collega a Windows, Android o browser, funziona subito.",
        "id": "Pemindai barcode USB atau Bluetooth standar, printer termal 58mm & 80mm, laci uang kasir, dan monitor dapur. Selama terhubung ke Windows, Android, atau browser, siap digunakan langsung.",
        "tr": "Herhangi bir standart USB veya Bluetooth barkod okuyucu, 80mm ve 58mm termal yazıcılar, para çekmeceleri ve mutfak ekranları. Windows, Android veya tarayıcıya bağlanabiliyorsa doğrudan çalışır."
    },
    "Can I manage an online storefront and multiple physical branches?": {
        "hi": "क्या मैं एक ऑनलाइन स्टोरफ्रंट और कई भौतिक शाखाओं का प्रबंधन कर सकता हूँ?",
        "ar": "هل يمكنني إدارة متجر إلكتروني وفروع متعددة على أرض الواقع؟",
        "es": "¿Puedo gestionar una tienda online y varias sucursales físicas a la vez?",
        "fr": "Puis-je gérer une boutique en ligne et plusieurs points de vente physiques ?",
        "pt": "Posso gerenciar uma loja virtual e várias filiais físicas ao mesmo tempo?",
        "de": "Kann ich einen Onlineshop und mehrere Filialen gleichzeitig verwalten?",
        "zh": "我能够同时管理线上数字微商城与多家线下实体连锁分店吗？",
        "ja": "オンラインショップと複数の実店舗を同時に一元管理できますか？",
        "ru": "Могу ли я управлять интернет-магазином и несколькими филиалами одновременно?",
        "it": "Posso gestire un negozio online e più filiali fisiche contemporaneamente?",
        "id": "Bisakah saya mengelola toko online dan beberapa cabang fisik sekaligus?",
        "tr": "Hem online bir mağazayı hem de birden fazla fiziksel şubeyi yönetebilir miyim?"
    },

    # Downloads
    "Download Native Counter Apps": {
        "hi": "नेटिव काउंटर ऐप्स डाउनलोड करें", "ar": "تحميل تطبيقات نقاط البيع الأصلية", "es": "Descarga las Apps Nativas para Mostrador",
        "fr": "Télécharger les applications de caisse natives", "pt": "Baixar Aplicativos Nativos para Balcão", "de": "Native Kassen-Apps herunterladen",
        "zh": "下载安装收银原生客户端应用", "ja": "専用レジアプリをダウンロード", "ru": "Скачать нативные приложения для кассы",
        "it": "Scarica le App Native per la Cassa", "id": "Unduh Aplikasi Kasir Resmi", "tr": "Yerel Kasa Uygulamalarını İndirin"
    },
    "Experience blazing-fast offline performance with direct thermal printer and scanner hardware drivers.": {
        "hi": "प्रत्यक्ष थर्मल प्रिंटर और स्कैनर हार्डवेयर ड्राइवरों के साथ अत्यधिक तेज़ ऑफ़लाइन प्रदर्शन का अनुभव करें।",
        "ar": "استمتع بأداء فائق السرعة بدون إنترنت مع مشغلات مباشرة لطابعات الإيصالات وأجهزة المسح.",
        "es": "Disfruta de un rendimiento veloz sin conexión con controladores directos para impresoras y escáneres.",
        "fr": "Bénéficiez d'une vitesse exceptionnelle hors-ligne grâce aux pilotes directs pour imprimantes et scanners.",
        "pt": "Tenha máxima velocidade de operação offline com drivers diretos para impressoras térmicas e leitores.",
        "de": "Erleben Sie rasante Offline-Leistung mit direkter Treiberunterstützung für Drucker und Barcodescanner.",
        "zh": "尊享直连热敏打印机与扫描外设的原生级硬件驱动，畅享毫无卡顿的极致离线收银体验。",
        "ja": "サーマルプリンターやスキャナーの直接ハードウェア制御により、圧倒的に高速なオフライン動作を体験。",
        "ru": "Оцените высочайшую скорость работы в офлайне с прямыми драйверами для принтеров и сканеров.",
        "it": "Prestazioni offline fulminee grazie ai driver diretti per stampanti termiche e lettori di codici.",
        "id": "Rasakan performa offline super cepat dengan driver langsung untuk printer termal dan scanner.",
        "tr": "Doğrudan termal yazıcı ve barkod okuyucu sürücüleriyle son derece hızlı çevrimdışı performans deneyimleyin."
    },
    "Download Android APK": {
        "hi": "एंड्रॉइड एपीके डाउनलोड करें", "ar": "تحميل ملف Android APK", "es": "Descargar APK para Android", "fr": "Télécharger l'APK Android",
        "pt": "Baixar APK para Android", "de": "Android APK herunterladen", "zh": "下载 Android APK 安装包", "ja": "Android用 APKをダウンロード",
        "ru": "Скачать APK для Android", "it": "Scarica APK per Android", "id": "Unduh APK Android", "tr": "Android APK İndir"
    },
    "Download Windows App": {
        "hi": "विंडोज ऐप डाउनलोड करें", "ar": "تحميل برنامج Windows", "es": "Descargar para Windows", "fr": "Télécharger pour Windows",
        "pt": "Baixar para Windows", "de": "Windows-App herunterladen", "zh": "下载 Windows 桌面端", "ja": "Windows版アプリをダウンロード",
        "ru": "Скачать для Windows", "it": "Scarica l'App per Windows", "id": "Unduh Aplikasi Windows", "tr": "Windows Uygulamasını İndir"
    },

    # Contact Info Cards
    "Head Office": {
        "hi": "मुख्य कार्यालय", "ar": "المقر الرئيسي", "es": "Oficina Central", "fr": "Siège social",
        "pt": "Matriz / Escritório Central", "de": "Hauptsitz", "zh": "总部地址", "ja": "本社所在地",
        "ru": "Главный офис", "it": "Sede Centrale", "id": "Kantor Pusat", "tr": "Genel Merkez"
    },
    "Call Center": {
        "hi": "कॉल सेंटर", "ar": "مركز الاتصال", "es": "Centro de Atención Telefónica", "fr": "Centre d'appels",
        "pt": "Central de Atendimento", "de": "Kundenservice-Telefon", "zh": "服务热线", "ja": "コールセンター",
        "ru": "Колл-центр", "it": "Centralino", "id": "Pusat Panggilan", "tr": "Çağrı Merkezi"
    },
    "Email": {
        "hi": "ईमेल", "ar": "البريد الإلكتروني", "es": "Correo", "fr": "E-mail",
        "pt": "E-mail", "de": "E-Mail", "zh": "客服邮箱", "ja": "メール",
        "ru": "Электронная почта", "it": "E-mail", "id": "Email", "tr": "E-posta"
    },
    "Working Hours": {
        "hi": "कार्य के घंटे", "ar": "ساعات العمل الرسمية", "es": "Horario de Atención", "fr": "Horaires d'ouverture",
        "pt": "Horário de Funcionamento", "de": "Öffnungszeiten", "zh": "工作时间", "ja": "営業時間",
        "ru": "График работы", "it": "Orari di Lavoro", "id": "Jam Kerja", "tr": "Çalışma Saatleri"
    }
}

langs = ['ar', 'es', 'hi', 'fr', 'pt', 'de', 'zh', 'ja', 'ru', 'it', 'id', 'tr']

# Update lang/*.json
for loc in langs:
    p = f"lang/{loc}.json"
    if os.path.exists(p):
        with open(p, 'r', encoding='utf-8') as f:
            d = json.load(f)
        for k, v in additional.items():
            if loc in v:
                d[k] = v[loc]
        with open(p, 'w', encoding='utf-8') as f:
            json.dump(d, f, ensure_ascii=False, indent=4)
        print(f"Updated {p}")

# Now build the full combined landing_translations.dart
# Read existing base from generate_landing_translations.py
import generate_landing_translations
full_dict = {}
for k, v in generate_landing_translations.translations.items():
    full_dict[k] = v
for k, v in additional.items():
    full_dict[k] = v

out_path = "mobile/lib/features/landing/models/landing_translations.dart"
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
    for loc in langs:
        f.write(f"    '{loc}': {{\n")
        for k, v in full_dict.items():
            if loc in v:
                val = v[loc].replace('\\', '\\\\').replace("'", "\\'").replace('$', '\\$')
                key_escaped = k.replace('\\', '\\\\').replace("'", "\\'").replace('$', '\\$')
                f.write(f"      '{key_escaped}': '{val}',\n")
        f.write("    },\n")
    f.write("""  };
}
""")

print(f"Successfully generated {out_path} with {len(full_dict)} total keys!")
