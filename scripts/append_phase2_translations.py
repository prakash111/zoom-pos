import json
import os

base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
lang_dir = os.path.join(base_dir, 'lang')
langs = ['ar', 'es', 'hi', 'fr', 'pt', 'de', 'zh', 'ja', 'ru', 'it', 'id', 'tr']

remaining_keys = {
    # Testimonials fallback
    "Switching to this platform doubled our online order throughput while cutting counter checkout times in half. The live inventory sync between web and store prevented overselling completely.": {
        "hi": "इस प्लेटफॉर्म पर स्विच करने से हमारे ऑनलाइन ऑर्डर थ्रूपुट दोगुने हो गए और काउंटर चेकआउट का समय आधा हो गया। वेब और स्टोर के बीच लाइव इन्वेंट्री सिंक ने ओवरसेलिंग को पूरी तरह से रोका।",
        "ar": "أدى التحول إلى هذه المنصة إلى مضاعفة حجم الطلبات عبر الإنترنت مع تقليل وقت الدفع عند الكاشير إلى النصف. كما منع المزامنة الفورية للمخزون بين المتجر الإلكتروني والفروع أي بيع زائد تماماً.",
        "es": "Cambiar a esta plataforma duplicó el volumen de pedidos online y redujo a la mitad el tiempo de cobro en mostrador. La sincronización de inventario en tiempo real evitó por completo el sobrestock.",
        "fr": "Passer à cette plateforme a doublé nos commandes en ligne tout en divisant par deux le temps de passage en caisse. La synchronisation des stocks en direct a totalement éliminé le surstock.",
        "pt": "Mudar para esta plataforma dobrou nossa capacidade de pedidos online e reduziu pela metade o tempo de checkout no balcão. A sincronização em tempo real evitou qualquer venda sem estoque.",
        "de": "Der Wechsel zu dieser Plattform hat unseren Online-Bestelldurchsatz verdoppelt und die Kassenabfertigung halbiert. Der Live-Bestandsabgleich verhinderte Überverkäufe vollständig.",
        "zh": "切换到该平台后，我们的线上订单吞吐量翻了一倍，同时柜台结账时间减少了一半。线上网店与线下实体门店之间的毫秒级实时库存同步彻底杜绝了超卖。",
        "ja": "このプラットフォームに移行したことで、オンライン注文の処理能力が2倍になり、レジの会計時間は半分に短縮されました。実店舗とWebのリアルタイム在庫同期により、売り越しを完全に防止できました。",
        "ru": "Переход на эту платформу удвоил объём онлайн-заказов и вдвое сократил время обслуживания на кассе. Синхронизация остатков в реальном времени полностью исключила оверселлинг.",
        "it": "Il passaggio a questa piattaforma ha raddoppiato la capacità degli ordini online dimezzando i tempi di cassa. La sincronizzazione del magazzino in tempo reale ha evitato l'overselling.",
        "id": "Beralih ke platform ini melipatgandakan pesanan online kami sekaligus memangkas separuh waktu antrean kasir. Sinkronisasi stok langsung mencegah kehabisan barang secara total.",
        "tr": "Bu platforma geçmek online sipariş hacmimizi ikiye katlarken kasa işlem sürelerini yarıya indirdi. Canlı stok senkronizasyonu fazla satışı tamamen engelledi."
    },
    "Founder & CEO · Urban Horizon Omnichannel": {
        "hi": "संस्थापक और सीईओ · अर्बन होराइजन ओम्नीचैनल",
        "ar": "المؤسس والرئيس التنفيذي · أوربان هورايزون",
        "es": "Fundador y CEO · Urban Horizon Omnichannel",
        "fr": "Fondateur & PDG · Urban Horizon Omnichannel",
        "pt": "Fundador e CEO · Urban Horizon Omnichannel",
        "de": "Gründer & CEO · Urban Horizon Omnichannel",
        "zh": "创始人兼首席执行官 · Urban Horizon 全渠道零售",
        "ja": "創業者 兼 CEO · Urban Horizon Omnichannel",
        "ru": "Основатель и CEO · Urban Horizon Omnichannel",
        "it": "Fondatore e CEO · Urban Horizon Omnichannel",
        "id": "Pendiri & CEO · Urban Horizon Omnichannel",
        "tr": "Kurucu ve CEO · Urban Horizon Omnichannel"
    },
    "During Black Friday rush, our fiber internet dropped for nearly 3 hours. The offline engine kept our counters ringing up sales without skipping a beat. It saved us thousands in lost revenue.": {
        "hi": "ब्लैक फ्राइडे की भीड़ के दौरान, हमारा फाइबर इंटरनेट लगभग 3 घंटे के लिए बंद हो गया। ऑफ़लाइन इंजन ने बिना रुके हमारे काउंटरों पर बिक्री जारी रखी। इसने हमें हजारों के नुकसान से बचाया।",
        "ar": "خلال ذروة مبيعات الجمعة البيضاء، انقطع اتصال الألياف الضوئية لما يقارب 3 ساعات. استمر المحرك غير المتصل بالإنترنت في تسجيل المبيعات دون أي توقف، مما وفر علينا آلاف الدولارات من الإيرادات الضائعة.",
        "es": "Durante el Black Friday, la conexión a internet se cayó durante casi 3 horas. El motor sin conexión permitió a nuestros mostradores seguir cobrando sin pausa, salvando miles en ventas perdidas.",
        "fr": "Pendant le rush du Black Friday, notre connexion fibre a coupé pendant 3 heures. Le moteur hors ligne a permis à nos caisses de continuer à encaisser sans interruption, nous évitant des milliers d'euros de pertes.",
        "pt": "Durante a correria da Black Friday, nossa internet caiu por quase 3 horas. O motor offline continuou registrando vendas sem falhar, salvando milhares de reais em receitas perdidas.",
        "de": "Am Black Friday fiel unser Glasfaser-Internet für fast 3 Stunden aus. Die Offline-Engine hielt unsere Kassen unterbrechungsfrei am Laufen und rettete uns vor enormen Umsatzausfällen.",
        "zh": "在黑五销售大促高峰期，门店光纤宽带突发中断了近3个小时。正是强大的离线收银引擎支撑所有收银台毫无阻碍地连续结账开单，为我们挽回了数以万计的营业损失。",
        "ja": "ブラックフライデーのピーク時に光回線が約3時間ダウンしましたが、オフラインエンジンのおかげでレジは1秒も止まることなく販売を継続。多額の売上損失を防ぐことができました。",
        "ru": "Во время пика Черной пятницы интернет отключился почти на 3 часа. Офлайн-движок позволил нашим кассам бесперебойно пробивать чеки, сохранив нам тысячи выручки.",
        "it": "Durante il Black Friday la connessione fibra è saltata per quasi 3 ore. Il motore offline ha continuato a far registrare le vendite senza interruzioni, salvando migliaia di euro.",
        "id": "Saat lonjakan penjualan Black Friday, internet fiber kami terputus hampir 3 jam. Sistem offline tetap memproses kasir tanpa jeda, menyelamatkan ribuan dolar dari potensi kerugian.",
        "tr": "Black Friday yoğunluğunda internetimiz yaklaşık 3 saat kesildi. Çevrimdışı motorumuz kasaların kesintisiz çalışmasını sağlayarak binlerce liralık kayıp geliri kurtardı."
    },
    "Head of Operations · Sterling Luxury Retail": {
        "hi": "संचालन प्रमुख · स्टर्लिंग लक्जरी रिटेल",
        "ar": "مدير العمليات · ستيرلينغ للمتاجر الفاخرة",
        "es": "Directora de Operaciones · Sterling Luxury Retail",
        "fr": "Directrice des Opérations · Sterling Luxury Retail",
        "pt": "Diretora de Operações · Sterling Luxury Retail",
        "de": "Leiterin operatives Geschäft · Sterling Luxury Retail",
        "zh": "运营总监 · Sterling 奢品零售集团",
        "ja": "オペレーション統括 · Sterling Luxury Retail",
        "ru": "Директор по операциям · Sterling Luxury Retail",
        "it": "Responsabile Operazioni · Sterling Luxury Retail",
        "id": "Kepala Operasional · Sterling Luxury Retail",
        "tr": "Operasyon Direktörü · Sterling Luxury Retail"
    },
    "We run 6 restaurant outlets. Having table QR ordering, instant KOT kitchen routing, and automated WhatsApp receipts in one unified system transformed our bottom line.": {
        "hi": "हम 6 रेस्तरां आउटलेट चलाते हैं। टेबल क्यूआर ऑर्डरिंग, त्वरित केओटी किचन रूटिंग और स्वचालित व्हाट्सएप रसीदें एक ही प्रणाली में होने से हमारे व्यवसाय में भारी सुधार हुआ।",
        "ar": "ندير 6 فروع للمطاعم. إن وجود طلبات الطاولات عبر رمز QR، وتوجيه أوامر المطبخ الفوري (KOT)، وإيصالات واتساب التلقائية في نظام واحد موحد أحدث فارقاً جذرياً في أرباحنا.",
        "es": "Gestionamos 6 restaurantes. Contar con pedidos mediante código QR en mesa, comandas KOT automáticas a cocina y recibos por WhatsApp en un único sistema transformó nuestros resultados.",
        "fr": "Nous exploitons 6 restaurants. Disposer des commandes QR à table, de la transmission instantanée KOT en cuisine et des reçus WhatsApp dans un système unifié a transformé notre rentabilité.",
        "pt": "Gerenciamos 6 restaurantes. Ter pedidos por QR Code na mesa, envio instantâneo de pedidos KOT para a cozinha e recibos automáticos por WhatsApp em um único sistema transformou nossa rentabilidade.",
        "de": "Wir betreiben 6 Restaurant-Filialen. QR-Code-Bestellung am Tisch, sofortiges KOT-Küchenrouting und automatisierte WhatsApp-Belege in einem System haben unseren Gewinn spürbar gesteigert.",
        "zh": "我们经营着6家连锁餐饮门店。统一系统内集成了桌面扫码点餐、毫秒级后厨KOT分单打印，以及全自动微信/WhatsApp电子发票小票推送，彻底革新了我们的盈利水平。",
        "ja": "6店舗のレストランを経営しています。テーブル席QR注文、厨房への即時KOT伝票ルーティング、WhatsApp電子レシートがひとつのシステムに統合され、収益性が劇的に改善しました。",
        "ru": "Мы управляем 6 ресторанами. QR-заказы за столиками, мгновенная передача чеков KOT на кухню и отправка чеков в WhatsApp в единой системе значительно повысили нашу прибыль.",
        "it": "Gestiamo 6 ristoranti. Avere ordinazioni al tavolo con QR code, smistamento KOT in cucina e scontrini via WhatsApp in un unico sistema ha trasformato la nostra redditività.",
        "id": "Kami mengelola 6 gerai restoran. Pemesanan meja via QR, perutean dapur KOT instan, dan struk WhatsApp otomatis dalam satu sistem terpadu melipatgandakan laba kami.",
        "tr": "6 restoran şubesi işletiyoruz. Masada QR sipariş, anında mutfak KOT yönlendirmesi ve otomatik WhatsApp fişlerinin tek bir sistemde olması kârlılığımızı kökten değiştirdi."
    },
    "Managing Director · Artisan Dine Group": {
        "hi": "प्रबंध निदेशक · आर्टिसन डाइन ग्रुप",
        "ar": "المدير العام · مجموعة أرتيزان داين",
        "es": "Director General · Artisan Dine Group",
        "fr": "Directeur Général · Artisan Dine Group",
        "pt": "Diretor Executivo · Artisan Dine Group",
        "de": "Geschäftsführer · Artisan Dine Group",
        "zh": "董事总经理 · Artisan Dine 餐饮集团",
        "ja": "マネージングディレクター · Artisan Dine Group",
        "ru": "Управляющий директор · Artisan Dine Group",
        "it": "Amministratore Delegato · Artisan Dine Group",
        "id": "Managing Director · Artisan Dine Group",
        "tr": "Genel Müdür · Artisan Dine Group"
    },

    # FAQs fallback
    "Does the POS continue working when the internet drops?": {
        "hi": "क्या इंटरनेट बंद होने पर भी पीओएस काम करता रहता है?",
        "ar": "هل يستمر نظام نقاط البيع (POS) في العمل عند انقطاع الإنترنت؟",
        "es": "¿El TPV sigue funcionando si se corta la conexión a internet?",
        "fr": "Le point de vente continue-t-il de fonctionner sans connexion internet ?",
        "pt": "O PDV continua funcionando quando a conexão com a internet cai?",
        "de": "Funktioniert das Kassensystem auch bei Internetausfall weiter?",
        "zh": "网络断开或没有网络时，POS收银系统还能继续正常工作吗？",
        "ja": "インターネット回線が切断された場合でも、レジは機能し続けますか？",
        "ru": "Продолжает ли работать POS-касса при отключении интернета?",
        "it": "Il punto vendita continua a funzionare se la connessione internet si interrompe?",
        "id": "Apakah POS tetap berfungsi saat koneksi internet terputus?",
        "tr": "İnternet kesildiğinde POS çalışmaya devam eder mi?"
    },
    "Yes, 100%. Product lookup, barcode scanning, cart calculations, and checkout continue running locally on your device without pause. When internet connection returns, offline sales synchronize automatically in the background with zero data loss.": {
        "hi": "हाँ, 100%। उत्पाद खोज, बारकोड स्कैनिंग, कार्ट गणना और चेकआउट बिना किसी रुकावट के आपके डिवाइस पर स्थानीय रूप से चलते रहते हैं। जब इंटरनेट वापस आता है, तो ऑफ़लाइन बिक्री शून्य डेटा हानि के साथ पृष्ठभूमि में स्वचालित रूप से सिंक हो जाती है।",
        "ar": "نعم، بنسبة 100%. البحث عن المنتجات، ومسح الباركود، وحسابات سلة المشتريات، وإتمام الدفع تستمر محلياً على جهازك دون انقطاع. وعند عودة الاتصال، تتم مزامنة المبيعات المسجلة أوفلاين تلقائياً في الخلفية دون أي فقدان للبيانات.",
        "es": "Sí, 100%. La búsqueda de artículos, lectura de código de barras, totales y cobros siguen funcionando localmente en tu terminal. Al restablecerse la red, las ventas se sincronizan solas en segundo plano sin pérdidas.",
        "fr": "Oui, à 100%. La recherche de produits, le scan de code-barres, le calcul du panier et l'encaissement continuent localement sur votre terminal. Dès que la connexion revient, les ventes hors ligne se synchronisent automatiquement sans perte de données.",
        "pt": "Sim, 100%. Busca de produtos, leitura de código de barras, cálculo de carrinho e pagamento continuam funcionando localmente no aparelho. Quando a rede volta, as vendas offline sincronizam automaticamente sem perdas.",
        "de": "Ja, zu 100%. Produktsuche, Barcode-Scanning, Warenkorbberechnung und Kassiervorgang laufen lokal auf Ihrem Gerät nahtlos weiter. Sobald das Internet zurückkehrt, synchronisieren sich alle Verkäufe automatisch im Hintergrund ohne Datenverlust.",
        "zh": "是的，100%支持完全离线。商品扫码查询、条码录入、购物车折扣与结账找零全部在您的设备本地高速运行，丝毫不受断网影响。一旦网络恢复，系统会在后台全自动双向同步所有离线销售账目，数据零丢失。",
        "ja": "はい、100%動作します。商品の検索、バーコードスキャン、カート計算、チェックアウト会計は端末ローカルで途切れることなく実行されます。インターネットが復旧すると、オフライン中の販売データがバックグラウンドで自動同期され、データ損失はゼロです。",
        "ru": "Да, на 100%. Поиск товаров, сканирование штрихкодов, расчет корзины и кассовый чек работают на устройстве локально без сбоев. При восстановлении сети все офлайн-чеки автоматически синхронизируются в фоне без потерь данных.",
        "it": "Sì, al 100%. La ricerca prodotti, la lettura del codice a barre, i calcoli del carrello e la cassa continuano a funzionare localmente sul terminale. Al ripristino della rete, le vendite offline si sincronizzano automaticamente in background senza alcuna perdita.",
        "id": "Ya, 100%. Pencarian produk, pemindaian barcode, hitungan keranjang, dan proses checkout tetap berjalan lokal di perangkat tanpa jeda. Saat internet aktif kembali, transaksi offline otomatis tersinkronisasi di latar belakang tanpa kehilangan data.",
        "tr": "Evet, %100. Ürün arama, barkod tarama, sepet hesaplamaları ve ödeme alma cihazınızda yerel olarak kesintisiz çalışır. İnternet bağlantısı geri geldiğinde, çevrimdışı satışlar sıfır veri kaybıyla arka planda otomatik senkronize edilir."
    },
    "Which hardware devices and printers are supported?": {
        "hi": "कौन से हार्डवेयर उपकरण और प्रिंटर समर्थित हैं?",
        "ar": "ما هي الأجهزة وطابعات الفواتير المدعومة؟",
        "es": "¿Qué dispositivos y tipos de impresoras son compatibles?",
        "fr": "Quels matériels et imprimantes sont pris en charge ?",
        "pt": "Quais dispositivos de hardware e impressoras são suportados?",
        "de": "Welche Hardware-Geräte und Belegdrucker werden unterstützt?",
        "zh": "支持哪些硬件收银设备、条码枪与小票打印机？",
        "ja": "どのハードウェア機器やプリンターに対応していますか？",
        "ru": "Какое оборудование и принтеры поддерживаются?",
        "it": "Quali dispositivi hardware e stampanti sono supportati?",
        "id": "Perangkat keras dan printer apa saja yang didukung?",
        "tr": "Hangi donanım cihazları ve yazıcılar destekleniyor?"
    },
    "Any standard USB or Bluetooth barcode scanner, 80mm and 58mm thermal receipt printers, auto-kick cash drawers, EMV/NFC card terminals, and kitchen display monitors. If it connects to Windows, Android, or browser, it works out of the box.": {
        "hi": "कोई भी मानक यूएसबी या ब्लूटूथ बारकोड स्कैनर, 80 मिमी और 58 मिमी थर्मल रसीद प्रिंटर, ऑटो-किक कैश ड्रॉअर, ईएमवी/एनएफसी कार्ड टर्मिनल और किचन डिस्प्ले मॉनिटर। यदि यह विंडोज़, एंड्रॉइड या ब्राउज़र से जुड़ता है, तो यह तुरंत काम करता है।",
        "ar": "أي ماسح باركود قياسي عبر USB أو Bluetooth، وطابعات الإيصالات الحرارية مقاس 80 مم و58 مم، وأدراج النقود التلقائية، وأجهزة الدفع الإلكتروني EMV/NFC، وشاشات عرض المطبخ. طالما يتصل بنظام Windows أو Android أو المتصفح، سيعمل مباشرة.",
        "es": "Cualquier lector de códigos USB o Bluetooth, impresoras térmicas de 80mm y 58mm, cajones portamonedas automáticos, datáfonos EMV/NFC y pantallas de cocina. Si se conecta a Windows, Android o navegador, funciona de inmediato.",
        "fr": "Tout lecteur code-barres USB ou Bluetooth, imprimantes thermiques 80mm et 58mm, tiroirs-caisses automatiques, terminaux de paiement EMV/NFC et écrans cuisine KDS. S'il se connecte à Windows, Android ou au navigateur, il est pris en charge immédiatement.",
        "pt": "Qualquer leitor de código de barras USB ou Bluetooth, impressoras térmicas de 80mm e 58mm, gavetas de dinheiro automáticas, maquininhas EMV/NFC e telas KDS de cozinha. Se conecta ao Windows, Android ou navegador, funciona de fábrica.",
        "de": "Jeder Standard-USB- oder Bluetooth-Barcodescanner, 80-mm- und 58-mm-Thermo-Bondrucker, automatische Kassenschubladen, EMV/NFC-Kartenterminals und Küchenmonitore. Jedes Gerät, das sich mit Windows, Android oder dem Browser verbindet, funktioniert sofort.",
        "zh": "支持任何标准 USB 或蓝牙条码扫描枪、80毫米与58毫米热敏小票打印机、自动弹开钱箱、EMV/NFC 刷卡终端及后厨显示大屏(KDS)。只要能连接 Windows、Android 或浏览器，即插即用无需复杂配置。",
        "ja": "一般的なUSB/Bluetoothバーコードスキャナー、80mmおよび58mmサーマルレシートプリンター、自動キャッシュドロアー、EMV/NFCカード決済端末、キッチンディスプレイ(KDS)に対応。Windows、Android、ブラウザに接続できれば、箱から出してすぐに利用可能です。",
        "ru": "Любые стандартные USB- или Bluetooth-сканеры штрихкодов, термопринтеры чеков 80 мм и 58 мм, денежные ящики с автооткрытием, терминалы EMV/NFC и кухонные экраны (KDS). Все, что подключается к Windows, Android или браузеру, готово к работе.",
        "it": "Qualsiasi lettore barcode USB o Bluetooth, stampanti termiche da 80mm e 58mm, cassetti rendiresto automatici, POS carta EMV/NFC e display da cucina. Se si collega a Windows, Android o browser, funziona subito senza configurazioni.",
        "id": "Semua pemindai barcode USB atau Bluetooth standar, printer kasir termal 80mm dan 58mm, laci kas otomatis, mesin kartu EMV/NFC, dan monitor dapur. Selama terhubung ke Windows, Android, atau peramban web, langsung dapat digunakan.",
        "tr": "Tüm standart USB veya Bluetooth barkod okuyucular, 80 mm ve 58 mm termal fiş yazıcıları, otomatik para çekmeceleri, EMV/NFC pos cihazları ve mutfak ekranları. Windows, Android veya tarayıcıya bağlanan her cihaz doğrudan çalışır."
    },
    "Is there a free trial without credit card?": {
        "hi": "क्या बिना क्रेडिट कार्ड के निःशुल्क परीक्षण उपलब्ध है?",
        "ar": "هل توجد فترة تجريبية مجانية بدون بطاقة ائتمان؟",
        "es": "¿Hay una prueba gratuita sin necesidad de tarjeta de crédito?",
        "fr": "Y a-t-il un essai gratuit sans carte bancaire ?",
        "pt": "Existe um teste grátis sem necessidade de cartão de crédito?",
        "de": "Gibt es eine kostenlose Testphase ohne Kreditkarte?",
        "zh": "是否有免信用卡绑定的完全免费试用？",
        "ja": "クレジットカードの登録なしで無料トライアルを利用できますか？",
        "ru": "Есть ли бесплатный пробный период без банковской карты?",
        "it": "C'è una prova gratuita senza carta di credito?",
        "id": "Apakah ada uji coba gratis tanpa memerlukan kartu kredit?",
        "tr": "Kredi kartı gerekmeden ücretsiz deneme süresi var mı?"
    },
    "You can launch your store workspace and test all features with zero risk. No credit card is required, no setup fees, and no long-term contracts. Upgrade or cancel anytime directly from your dashboard.": {
        "hi": "आप अपने स्टोर का कार्यक्षेत्र लॉन्च कर सकते हैं और बिना किसी जोखिम के सभी सुविधाओं का परीक्षण कर सकते हैं। कोई क्रेडिट कार्ड आवश्यक नहीं है, कोई सेटअप शुल्क नहीं है, और कोई दीर्घकालिक अनुबंध नहीं है। अपने डैशबोर्ड से सीधे कभी भी अपग्रेड या रद्द करें।",
        "ar": "يمكنك بدء مساحة عمل متجرك وتجربة جميع الميزات بدون أي مخاطرة. لا يلزم وجود بطاقة ائتمان، ولا رسوم إعداد، ولا عقود طويلة الأجل. يمكنك الترقية أو الإلغاء في أي وقت مباشرة من لوحة التحكم.",
        "es": "Puedes abrir el espacio de tu tienda y probar todas las funciones sin riesgo. No se pide tarjeta, ni costes de alta ni permanencias. Cambia o cancela cuando quieras desde tu panel.",
        "fr": "Créez l'espace de votre magasin et testez toutes les fonctionnalités sans risque. Aucune carte bancaire requise, aucun frais de dossier, aucun engagement. Modifiez ou résiliez quand vous voulez.",
        "pt": "Você pode iniciar sua loja e testar todos os recursos sem risco. Não solicitamos cartão, não há taxa de adesão nem fidelidade. Faça upgrade ou cancele quando quiser pelo painel.",
        "de": "Sie können Ihren Store-Arbeitsbereich starten und alle Funktionen risikofrei testen. Keine Kreditkarte erforderlich, keine Einrichtungsgebühr, keine Vertragslaufzeiten. Jederzeit flexibel kündbar.",
        "zh": "您可以随时极速创建并开启您的专属门店工作空间，零风险畅享所有商业功能。无需绑定任何信用卡，无任何初始部署费用，无长期合同捆绑。在管理控制台随时一键升级或取消。",
        "ja": "リスクなしで店舗ワークスペースを開設し、全機能を無料でお試しいただけます。クレジットカード登録不要、初期費用なし、長期契約の縛りもありません。管理画面からいつでもアップグレードや解約が可能です。",
        "ru": "Вы можете запустить пробное рабочее пространство магазина без риска. Кредитная карта не требуется, скрытых платежей нет, без контрактов. Улучшайте тариф или отменяйте подписку в любой момент из панели.",
        "it": "Puoi attivare lo spazio del tuo negozio e provare ogni funzione a rischio zero. Nessuna carta richiesta, nessun costo di attivazione e nessun vincolo. Aggiorna o annulla direttamente dal pannello.",
        "id": "Anda dapat langsung membuat ruang kerja toko dan mencoba seluruh fitur tanpa risiko. Tanpa perlu kartu kredit, tanpa biaya pemasangan, dan tanpa kontrak mengikat. Bebas tingkatkan atau batalkan kapan saja.",
        "tr": "Mağazanızı hemen açıp tüm özellikleri sıfır riskle deneyebilirsiniz. Kredi kartı gerekmez, kurulum ücreti ve taahhüt yoktur. İstediğiniz an doğrudan panelinizden yükseltin veya iptal edin."
    },

    # Limits and billing
    "Invoices/mo": {
        "hi": "इनवॉइस/माह", "ar": "فواتير/شهر", "es": "Facturas/mes", "fr": "Factures/mois",
        "pt": "Faturas/mês", "de": "Rechnungen/Monat", "zh": "发票/月", "ja": "請求書/月",
        "ru": "Счетов/мес", "it": "Fatture/mese", "id": "Faktur/bln", "tr": "Fatura/ay"
    },
    "Products": {
        "hi": "उत्पाद", "ar": "منتجات", "es": "Productos", "fr": "Produits",
        "pt": "Produtos", "de": "Produkte", "zh": "产品", "ja": "商品",
        "ru": "Товаров", "it": "Prodotti", "id": "Produk", "tr": "Ürün"
    },
    "Devices": {
        "hi": "उपकरण", "ar": "أجهزة", "es": "Dispositivos", "fr": "Appareils",
        "pt": "Dispositivos", "de": "Geräte", "zh": "设备", "ja": "端末",
        "ru": "Устройств", "it": "Dispositivi", "id": "Perangkat", "tr": "Cihaz"
    },
    "Staff": {
        "hi": "कर्मचारी", "ar": "موظفون", "es": "Personal", "fr": "Personnel",
        "pt": "Colaboradores", "de": "Mitarbeiter", "zh": "员工", "ja": "スタッフ",
        "ru": "Сотрудников", "it": "Personale", "id": "Staf", "tr": "Personel"
    },
    "/monthly": {
        "hi": "/माह", "ar": "/شهرياً", "es": "/mes", "fr": "/mois",
        "pt": "/mês", "de": "/Monat", "zh": "/月", "ja": "/月",
        "ru": "/мес", "it": "/mese", "id": "/bulan", "tr": "/ay"
    },
    "/yearly": {
        "hi": "/वर्ष", "ar": "/سنوياً", "es": "/año", "fr": "/an",
        "pt": "/ano", "de": "/Jahr", "zh": "/年", "ja": "/年",
        "ru": "/год", "it": "/anno", "id": "/tahun", "tr": "/yıl"
    },

    # Plans
    "Starter": {
        "hi": "स्टार्टर", "ar": "الأساسية", "es": "Básico", "fr": "Démarrage",
        "pt": "Iniciante", "de": "Starter", "zh": "入门版", "ja": "スターター",
        "ru": "Стартовый", "it": "Starter", "id": "Pemula", "tr": "Başlangıç"
    },
    "Essential tools for single-counter stores": {
        "hi": "सिंगल-काउंटर स्टोर के लिए आवश्यक उपकरण", "ar": "الأدوات الأساسية لمتاجر الكاشير الواحد",
        "es": "Herramientas esenciales para tiendas de un solo mostrador", "fr": "Les outils essentiels pour les boutiques à caisse unique",
        "pt": "Ferramentas essenciais para lojas de balcão único", "de": "Grundlegende Werkzeuge für Einzelkassen-Geschäfte",
        "zh": "单收银台零售与餐饮门店必备开店利器", "ja": "単一レジ店舗に必要な基本機能を網羅",
        "ru": "Базовые инструменты для магазинов с одной кассой", "it": "Strumenti essenziali per negozi con singola cassa",
        "id": "Fitur esensial untuk toko dengan satu kasir", "tr": "Tek kasalı mağazalar için temel araçlar"
    },
    "Professional": {
        "hi": "प्रोफेशनल", "ar": "الاحترافية", "es": "Profesional", "fr": "Professionnel",
        "pt": "Profissional", "de": "Professional", "zh": "专业版", "ja": "プロフェッショナル",
        "ru": "Профессиональный", "it": "Professionale", "id": "Profesional", "tr": "Profesyonel"
    },
    "Best for growing multi-counter retail & restaurants": {
        "hi": "बढ़ते मल्टी-काउंटर रिटेल और रेस्तरां के लिए सर्वश्रेष्ठ", "ar": "الخيار الأمثل للمتاجر والمطاعم سريعة النمو ذات الكاشيرات المتعددة",
        "es": "Ideal para comercios y restaurantes en crecimiento con múltiples mostradores", "fr": "Idéal pour les commerces et restaurants en pleine croissance multi-caisses",
        "pt": "Ideal para varejos e restaurantes em expansão com múltiplos caixas", "de": "Optimal für wachsende Geschäfte und Restaurants mit mehreren Kassen",
        "zh": "快速成长的多柜台连锁零售商超与大中型餐饮首选", "ja": "複数レジを展開する成長中の小売店・飲食店に最適",
        "ru": "Идеально для растущих магазинов и ресторанов с несколькими кассами", "it": "Perfetto per negozi e ristoranti in crescita con più casse",
        "id": "Terbaik untuk ritel & restoran berkembang dengan banyak kasir", "tr": "Büyüyen çok kasalı perakende ve restoranlar için en iyisi"
    },
    "Enterprise": {
        "hi": "एंटरप्राइज", "ar": "المؤسسات", "es": "Empresarial", "fr": "Entreprise",
        "pt": "Corporativo", "de": "Enterprise", "zh": "企业旗舰版", "ja": "エンタープライズ",
        "ru": "Корпоративный", "it": "Enterprise", "id": "Enterprise", "tr": "Kurumsal"
    },
    "Full custom deployment with white-label branding": {
        "hi": "व्हाइट-लेबल ब्रांडिंग के साथ पूर्ण कस्टम परिनियोजन", "ar": "نشر مخصص بالكامل مع علامة تجارية خاصة (White-Label)",
        "es": "Despliegue totalmente personalizado con marca blanca", "fr": "Déploiement complet sur mesure avec personnalisation en marque blanche",
        "pt": "Implantação totalmente personalizada com marca própria (White-Label)", "de": "Vollständig maßgeschneiderte Bereitstellung mit White-Label-Branding",
        "zh": "支持独立私有化专属部署与全套品牌白标定制", "ja": "自社ブランド完全対応のフルカスタム導入プラン",
        "ru": "Индивидуальное развертывание с брендированием под ключ (White-Label)", "it": "Implementazione personalizzata con branding in white-label",
        "id": "Penerapan kustom penuh dengan branding white-label", "tr": "White-label markalama ile tam özel dağıtım"
    },
    "1 Store Location": {
        "hi": "1 स्टोर स्थान", "ar": "موقع متجر واحد", "es": "1 Ubicación de Tienda", "fr": "1 emplacement de boutique",
        "pt": "1 Local de Loja", "de": "1 Filialstandort", "zh": "1 个门店位置", "ja": "1 店舗",
        "ru": "1 торговая точка", "it": "1 Punto Vendita", "id": "1 Lokasi Toko", "tr": "1 Mağaza Konumu"
    },
    "Receipt Printing": {
        "hi": "रसीद छपाई", "ar": "طباعة الإيصالات", "es": "Impresión de Recibos", "fr": "Impression des reçus",
        "pt": "Impressão de Recibos", "de": "Belegdruck", "zh": "小票发票打印", "ja": "レシート印刷",
        "ru": "Печать чеков", "it": "Stampa Scontrini", "id": "Cetak Struk", "tr": "Fiş Yazdırma"
    },
    "Standard Email Support": {
        "hi": "मानक ईमेल सहायता", "ar": "دعم فني قياسي عبر البريد الإلكتروني", "es": "Soporte Estándar por Email", "fr": "Support standard par e-mail",
        "pt": "Suporte Padrão por E-mail", "de": "Standard-E-Mail-Support", "zh": "标准电子邮件技术支持", "ja": "標準メールサポート",
        "ru": "Стандартная поддержка по email", "it": "Supporto Standard via Email", "id": "Dukungan Email Standar", "tr": "Standart E-posta Desteği"
    },
    "Multi-Location Support": {
        "hi": "मल्टी-लोकेशन सहायता", "ar": "دعم المواقع المتعددة", "es": "Soporte Multi-Ubicación", "fr": "Support multi-sites",
        "pt": "Suporte Multi-Unidades", "de": "Filialnetzwerk-Unterstützung", "zh": "多门店/多网点统管支持", "ja": "複数拠点・多店舗対応",
        "ru": "Поддержка сети филиалов", "it": "Supporto Multi-Sede", "id": "Dukungan Banyak Cabang", "tr": "Çoklu Şube Desteği"
    },
    "Table Floor Management & KOT": {
        "hi": "टेबल फ्लोर प्रबंधन और केओटी", "ar": "إدارة الطاولات وأوامر المطبخ (KOT)", "es": "Gestión de Mesas y KOT para Cocina", "fr": "Gestion des tables en salle et KOT cuisine",
        "pt": "Gestão de Mesas e KOT para Cozinha", "de": "Tischplan-Management & KOT-Küche", "zh": "堂食桌台房台管理与后厨KOT分单", "ja": "テーブル席フロア管理＆KOT伝票",
        "ru": "Управление столиками и чеками KOT на кухню", "it": "Gestione Tavoli e Comande KOT Cucina", "id": "Manajemen Meja & KOT Dapur", "tr": "Masa Planı ve Mutfak KOT Yönetimi"
    },
    "Priority WhatsApp Support": {
        "hi": "प्राथमिकता व्हाट्सएप सहायता", "ar": "دعم فني ذو أولوية عبر واتساب", "es": "Soporte Prioritario por WhatsApp", "fr": "Support prioritaire par WhatsApp",
        "pt": "Suporte Prioritário via WhatsApp", "de": "Prioritäts-Support via WhatsApp", "zh": "专属 WhatsApp 优先技术支持", "ja": "優先WhatsAppサポート",
        "ru": "Приоритетная поддержка в WhatsApp", "it": "Supporto Prioritario WhatsApp", "id": "Dukungan Prioritas via WhatsApp", "tr": "Öncelikli WhatsApp Desteği"
    },
    "Dedicated Database & Domain": {
        "hi": "समर्पित डेटाबेस और डोमेन", "ar": "قاعدة بيانات ونطاق مخصص", "es": "Base de Datos y Dominio Dedicados", "fr": "Base de données et domaine dédiés",
        "pt": "Banco de Dados e Domínio Dedicados", "de": "Dedizierte Datenbank & Domain", "zh": "专属独立数据库与独立域名", "ja": "専用データベース＆独自ドメイン",
        "ru": "Выделенная база данных и домен", "it": "Database e Dominio Dedicati", "id": "Basis Data & Domain Khusus", "tr": "Özel Veritabanı ve Alan Adı"
    },
    "White-Label Custom Branding": {
        "hi": "व्हाइट-लेबल कस्टम ब्रांडिंग", "ar": "علامة تجارية مخصصة بالكامل (White-Label)", "es": "Marca Blanca Totalmente Personalizada", "fr": "Personnalisation complète en marque blanche",
        "pt": "Marca Própria White-Label Personalizada", "de": "Vollständiges White-Label-Branding", "zh": "全套品牌企业白标自主定制", "ja": "完全ホワイトレーベル・ブランドカスタマイズ",
        "ru": "Полное брендирование под ключ (White-Label)", "it": "Personalizzazione completa in White-Label", "id": "Kustomisasi Penuh White-Label", "tr": "Tamamen Özel White-Label Markalama"
    },
    "Automated Cloud Backups": {
        "hi": "स्वचालित क्लाउड बैकअप", "ar": "نسخ احتياطية سحابية تلقائية", "es": "Copias de Seguridad Automáticas en la Nube", "fr": "Sauvegardes cloud automatisées",
        "pt": "Backups Automatizados na Nuvem", "de": "Automatische Cloud-Backups", "zh": "全自动多节点云端灾备", "ja": "自動クラウドバックアップ",
        "ru": "Автоматические бэкапы в облако", "it": "Backup Automatici su Cloud", "id": "Pencadangan Cloud Otomatis", "tr": "Otomatik Bulut Yedekleme"
    },
    "Dedicated Account Manager": {
        "hi": "समर्पित खाता प्रबंधक", "ar": "مدير حساب مخصص", "es": "Gestor de Cuenta Dedicado", "fr": "Gestionnaire de compte dédié",
        "pt": "Gerente de Contas Dedicado", "de": "Persönlicher Kundenbetreuer", "zh": "1对1专属大客户服务经理", "ja": "専任アカウントマネージャー",
        "ru": "Выделенный аккаунт-менеджер", "it": "Account Manager Dedicato", "id": "Manajer Akun Khusus", "tr": "Özel Müşteri Temsilcisi"
    },

    # Interactive Contact Form & Footer
    "Inquiry Received!": {
        "hi": "पूछताछ प्राप्त हुई!", "ar": "تم استلام استفسارك بنجاح!", "es": "¡Consulta Recibida!", "fr": "Demande reçue !",
        "pt": "Solicitação Recebida!", "de": "Anfrage erhalten!", "zh": "咨询已成功送达！", "ja": "お問い合わせを受け付けました！",
        "ru": "Заявка успешно получена!", "it": "Richiesta Ricevuta!", "id": "Pertanyaan Telah Diterima!", "tr": "Talebiniz Alındı!"
    },
    "Thank you for reaching out. Our business solutions team has received your message and will contact you via email or phone shortly.": {
        "hi": "संपर्क करने के लिए धन्यवाद। हमारी व्यापार समाधान टीम को आपका संदेश मिल गया है और वह शीघ्र ही ईमेल या फोन के माध्यम से आपसे संपर्क करेगी।",
        "ar": "شكراً لتواصلك معنا. لقد تلقى فريق حلول الأعمال رسالتك وسيتواصل معك عبر البريد الإلكتروني أو الهاتف قريباً.",
        "es": "Gracias por contactarnos. Nuestro equipo comercial ha recibido tu mensaje y se pondrá en contacto por email o teléfono a la brevedad.",
        "fr": "Merci de nous avoir contactés. Notre équipe commerciale a bien reçu votre message et vous recontactera très rapidement par e-mail ou téléphone.",
        "pt": "Obrigado pelo contato. Nossa equipe comercial já recebeu sua mensagem e entrará em contato por e-mail ou telefone em breve.",
        "de": "Vielen Dank für Ihre Kontaktaufnahme. Unser Vertriebsteam hat Ihre Nachricht erhalten und wird sich in Kürze per E-Mail oder Telefon bei Ihnen melden.",
        "zh": "感谢您的咨询。我们的专业业务顾问已收到您的需求，将通过电子邮件或电话尽快与您取得联系。",
        "ja": "お問い合わせいただきありがとうございます。専任チームがメッセージを確認いたしました。近日中にメールまたはお電話にてご連絡申し上げます。",
        "ru": "Спасибо за обращение. Наша команда решений для бизнеса получила сообщение и свяжется с вами по почте или телефону в ближайшее время.",
        "it": "Grazie per averci contattato. Il nostro team ha ricevuto la tua richiesta e ti ricontatterà a breve via email o telefono.",
        "id": "Terima kasih telah menghubungi kami. Tim kami telah menerima pesan Anda dan akan segera menghubungi via email atau telepon.",
        "tr": "Bize ulaştığınız için teşekkür ederiz. Ekibimiz mesajınızı aldı ve en kısa sürede e-posta veya telefon ile iletişime geçecektir."
    },
    "Send Another Message": {
        "hi": "एक और संदेश भेजें", "ar": "إرسال رسالة أخرى", "es": "Enviar Otro Mensaje", "fr": "Envoyer un autre message",
        "pt": "Enviar Outra Mensagem", "de": "Weitere Nachricht senden", "zh": "发送另一条留言", "ja": "別のメッセージを送信",
        "ru": "Отправить еще сообщение", "it": "Invia un altro messaggio", "id": "Kirim Pesan Lainnya", "tr": "Başka Bir Mesaj Gönder"
    },
    "Get in Touch with Our Team": {
        "hi": "हमारी टीम से संपर्क करें", "ar": "تواصل مع فريقنا المتخصص", "es": "Ponte en Contacto con Nuestro Equipo", "fr": "Entrez en contact avec notre équipe",
        "pt": "Fale com Nossa Equipe", "de": "Kontaktieren Sie unser Team", "zh": "联系我们的专业团队", "ja": "専任チームにお問い合わせ",
        "ru": "Свяжитесь с нашей командой", "it": "Contatta il nostro team", "id": "Hubungi Tim Kami", "tr": "Ekibimizle İletişime Geçin"
    },
    "Have questions about onboarding, hardware compatibility, or pricing? Fill out the form below.": {
        "hi": "क्या ऑनबोर्डिंग, हार्डवेयर अनुकूलता, या मूल्य निर्धारण के बारे में प्रश्न हैं? नीचे दिया गया फॉर्म भरें।",
        "ar": "هل لديك أسئلة حول التشغيل، وتوافق الأجهزة، أو خطط الأسعار؟ املأ النموذج أدناه.",
        "es": "¿Tienes dudas sobre la puesta en marcha, compatibilidad de dispositivos o tarifas? Rellena el formulario.",
        "fr": "Des questions sur l'installation, le matériel compatible ou les tarifs ? Remplissez le formulaire ci-dessous.",
        "pt": "Dúvidas sobre implantação, compatibilidade de equipamentos ou preços? Preencha o formulário abaixo.",
        "de": "Haben Sie Fragen zur Einrichtung, Hardware-Kompatibilität oder Preisen? Füllen Sie das Formular aus.",
        "zh": "对系统上线部署、硬件收银机兼容性或商业定价方案有疑问？请填写下表与我们联系。",
        "ja": "導入サポート、対応ハードウェア、料金プランについてご質問がありますか？以下のフォームよりお気軽にご送信ください。",
        "ru": "Есть вопросы по подключению, совместимости оборудования или ценам? Заполните форму ниже.",
        "it": "Hai domande su configurazione, compatibilità hardware o tariffe? Compila il modulo sottostante.",
        "id": "Punya pertanyaan seputar panduan awal, kecocokan perangkat, atau harga? Isi formulir di bawah ini.",
        "tr": "Kurulum, donanım uyumluluğu veya fiyatlandırma hakkında sorularınız mı var? Aşağıdaki formu doldurun."
    },
    "Full Name *": {
        "hi": "पूरा नाम *", "ar": "الاسم الكامل *", "es": "Nombre Completo *", "fr": "Nom complet *",
        "pt": "Nome Completo *", "de": "Vollständiger Name *", "zh": "姓名 *", "ja": "お名前 *",
        "ru": "Полное имя *", "it": "Nome e Cognome *", "id": "Nama Lengkap *", "tr": "Ad Soyad *"
    },
    "Business Email *": {
        "hi": "व्यावसायिक ईमेल *", "ar": "البريد الإلكتروني للعمل *", "es": "Correo Corporativo *", "fr": "E-mail professionnel *",
        "pt": "E-mail Comercial *", "de": "Geschäftliche E-Mail *", "zh": "企业工作邮箱 *", "ja": "ビジネス用メールアドレス *",
        "ru": "Рабочий Email *", "it": "Email Aziendale *", "id": "Email Bisnis *", "tr": "İş E-postası *"
    },
    "Phone Number": {
        "hi": "फ़ोन नंबर", "ar": "رقم الهاتف", "es": "Número de Teléfono", "fr": "Numéro de téléphone",
        "pt": "Telefone de Contato", "de": "Telefonnummer", "zh": "联系电话", "ja": "電話番号",
        "ru": "Номер телефона", "it": "Numero di Telefono", "id": "Nomor Telepon", "tr": "Telefon Numarası"
    },
    "Store / Business Type": {
        "hi": "स्टोर / व्यापार का प्रकार", "ar": "نوع المتجر / النشاط التجاري", "es": "Tipo de Tienda / Negocio", "fr": "Type de commerce / activité",
        "pt": "Tipo de Loja / Negócio", "de": "Art des Geschäfts / Branche", "zh": "门店/商业业态类型", "ja": "店舗・事業形態",
        "ru": "Тип магазина / бизнеса", "it": "Tipo di Negozio / Attività", "id": "Jenis Toko / Bisnis", "tr": "Mağaza / İşletme Türü"
    },
    "Select business type…": {
        "hi": "व्यापार का प्रकार चुनें…", "ar": "اختر نوع النشاط التجاري…", "es": "Selecciona el tipo de negocio…", "fr": "Sélectionnez votre type d'activité…",
        "pt": "Selecione o tipo de negócio…", "de": "Branche auswählen…", "zh": "选择所属行业业态…", "ja": "業種を選択してください…",
        "ru": "Выберите сферу деятельности…", "it": "Seleziona tipo di attività…", "id": "Pilih jenis usaha…", "tr": "İşletme türünü seçin…"
    },
    "Subject": {
        "hi": "विषय", "ar": "الموضوع", "es": "Asunto", "fr": "Objet",
        "pt": "Assunto", "de": "Betreff", "zh": "主题", "ja": "件名",
        "ru": "Тема", "it": "Oggetto", "id": "Subjek", "tr": "Konu"
    },
    "Questions before signing up": {
        "hi": "साइन अप करने से पहले प्रश्न", "ar": "استفسارات قبل التسجيل", "es": "Preguntas antes de contratar", "fr": "Questions avant inscription",
        "pt": "Dúvidas antes de assinar", "de": "Fragen vor der Registrierung", "zh": "注册前咨询与需求沟通", "ja": "ご登録前の事前相談",
        "ru": "Вопросы перед регистрацией", "it": "Domande prima dell'iscrizione", "id": "Pertanyaan sebelum mendaftar", "tr": "Kayıt öncesi sorular"
    },
    "Message *": {
        "hi": "संदेश *", "ar": "نص الرسالة *", "es": "Mensaje *", "fr": "Votre message *",
        "pt": "Mensagem *", "de": "Ihre Nachricht *", "zh": "留言详细内容 *", "ja": "メッセージ内容 *",
        "ru": "Текст сообщения *", "it": "Messaggio *", "id": "Isi Pesan *", "tr": "Mesajınız *"
    },
    "Tell us a bit about your business requirements…": {
        "hi": "हमें अपनी व्यावसायिक आवश्यकताओं के बारे में बताएं…", "ar": "أخبرنا قليلاً عن متطلبات عملك ونشاطك…",
        "es": "Cuéntanos un poco sobre las necesidades de tu negocio…", "fr": "Parlez-nous des besoins et attentes de votre entreprise…",
        "pt": "Conte-nos um pouco sobre as necessidades da sua empresa…", "de": "Beschreiben Sie kurz Ihre betrieblichen Anforderungen…",
        "zh": "简要介绍您的门店规模、业务需求与使用场景…", "ja": "店舗の運営規模やご要望についてお聞かせください…",
        "ru": "Расскажите немного о задачах и требованиях вашего бизнеса…", "it": "Raccontaci le esigenze del tuo punto vendita…",
        "id": "Ceritakan sedikit tentang kebutuhan usaha Anda…", "tr": "İşletmenizin gereksinimlerinden kısaca bahsedin…"
    },
    "Please enter your name": {
        "hi": "कृपया अपना नाम दर्ज करें", "ar": "يرجى إدخال اسمك", "es": "Por favor introduce tu nombre", "fr": "Veuillez saisir votre nom",
        "pt": "Por favor, informe seu nome", "de": "Bitte geben Sie Ihren Namen ein", "zh": "请输入您的姓名", "ja": "お名前を入力してください",
        "ru": "Пожалуйста, введите ваше имя", "it": "Inserisci il tuo nome", "id": "Harap masukkan nama Anda", "tr": "Lütfen adınızı girin"
    },
    "Please enter your email": {
        "hi": "कृपया अपना ईमेल दर्ज करें", "ar": "يرجى إدخال بريدك الإلكتروني", "es": "Por favor introduce tu email", "fr": "Veuillez saisir votre e-mail",
        "pt": "Por favor, informe seu e-mail", "de": "Bitte geben Sie Ihre E-Mail-Adresse ein", "zh": "请输入您的电子邮箱", "ja": "メールアドレスを入力してください",
        "ru": "Пожалуйста, введите ваш email", "it": "Inserisci la tua email", "id": "Harap masukkan email Anda", "tr": "Lütfen e-posta adresinizi girin"
    },
    "Please enter a valid email address": {
        "hi": "कृपया एक मान्य ईमेल पता दर्ज करें", "ar": "يرجى إدخال عنوان بريد إلكتروني صحيح", "es": "Por favor introduce un email válido", "fr": "Veuillez saisir une adresse e-mail valide",
        "pt": "Por favor, informe um e-mail válido", "de": "Bitte geben Sie eine gültige E-Mail-Adresse ein", "zh": "请输入有效的电子邮箱地址", "ja": "有効なメールアドレスを入力してください",
        "ru": "Пожалуйста, введите корректный адрес email", "it": "Inserisci un indirizzo email valido", "id": "Harap masukkan alamat email yang valid", "tr": "Lütfen geçerli bir e-posta adresi girin"
    },
    "Please enter your message": {
        "hi": "कृपया अपना संदेश लिखें", "ar": "يرجى إدخال رسالتك", "es": "Por favor introduce tu mensaje", "fr": "Veuillez saisir votre message",
        "pt": "Por favor, escreva sua mensagem", "de": "Bitte geben Sie eine Nachricht ein", "zh": "请输入您的留言内容", "ja": "メッセージを入力してください",
        "ru": "Пожалуйста, введите сообщение", "it": "Inserisci il tuo messaggio", "id": "Harap masukkan pesan Anda", "tr": "Lütfen mesajınızı yazın"
    },
    "Message must be at least 5 characters long": {
        "hi": "संदेश कम से कम 5 अक्षरों का होना चाहिए", "ar": "يجب أن تحتوي الرسالة على 5 أحرف على الأقل", "es": "El mensaje debe tener al menos 5 caracteres", "fr": "Le message doit contenir au moins 5 caractères",
        "pt": "A mensagem deve ter pelo menos 5 caracteres", "de": "Die Nachricht muss mindestens 5 Zeichen enthalten", "zh": "留言内容至少需要 5 个字符", "ja": "メッセージは5文字以上で入力してください",
        "ru": "Сообщение должно содержать не менее 5 символов", "it": "Il messaggio deve contenere almeno 5 caratteri", "id": "Pesan minimal harus terdiri dari 5 karakter", "tr": "Mesaj en az 5 karakter uzunluğunda olmalıdır"
    },
    "Restaurant / Cafe / QSR": {
        "hi": "रेस्तरां / कैफे / क्यूएसआर", "ar": "مطاعم ومقاهي ووجبات سريعة", "es": "Restaurantes, Cafeterías y Comida Rápida", "fr": "Restaurants, Cafés et Restauration rapide",
        "pt": "Restaurantes, Cafés e Fast Food", "de": "Restaurant / Café / Schnellgastronomie", "zh": "餐饮美食 / 咖啡茶饮 / 快餐快饮", "ja": "飲食店・カフェ・ファストフード",
        "ru": "Рестораны / Каफे / Фастфуд", "it": "Ristoranti, Caffetterie e Fast Food", "id": "Restoran / Kafe / Cepat Saji", "tr": "Restoran / Kafe / Fast Food"
    },
    "Salon & Spa": {
        "hi": "सैलून और स्पा", "ar": "صالونات الحلاقة والتجميل والسبا", "es": "Salón de Belleza y Spa", "fr": "Salons de coiffure et Spas",
        "pt": "Salão de Beleza e Spa", "de": "Friseur & Kosmetikstudio", "zh": "美业沙龙 / 养生SPA / 美甲美睫", "ja": "サロン＆スパ・美容室",
        "ru": "Салоны красоты и Спа", "it": "Saloni di Bellezza e Spa", "id": "Salon & Spa", "tr": "Kuaför & Spa"
    },
    "Pharmacy": {
        "hi": "फार्मेसी और दवा स्टोर", "ar": "الصيدليات والأدوية", "es": "Farmacias", "fr": "Pharmacies et Parapharmacies",
        "pt": "Farmácias e Drogarias", "de": "Apotheke & Drogerie", "zh": "医药健康与连锁药房", "ja": "調剤薬局・ドラッグストア",
        "ru": "Аптеки и оптика", "it": "Farmacie e Parafarmacie", "id": "Apotek & Farmasi", "tr": "Eczane"
    },
    "Service Business": {
        "hi": "सेवा व्यवसाय", "ar": "الأعمال والخدمات المهنية", "es": "Empresas de Servicios", "fr": "Entreprises de services",
        "pt": "Prestadores de Serviços", "de": "Dienstleistungsunternehmen", "zh": "专业服务型企业", "ja": "サービス業全般",
        "ru": "Сфера услуг", "it": "Aziende di Servizi", "id": "Bisnis Jasa / Layanan", "tr": "Hizmet İşletmeleri"
    },
    "Other": {
        "hi": "अन्य", "ar": "أخرى", "es": "Otro", "fr": "Autre",
        "pt": "Outro", "de": "Sonstiges", "zh": "其他行业", "ja": "その他",
        "ru": "Другое", "it": "Altro", "id": "Lainnya", "tr": "Diğer"
    },
    "Connect with Our Sales & Support Team": {
        "hi": "हमारी बिक्री और सहायता टीम से जुड़ें", "ar": "تواصل مع فريق المبيعات والدعم الفني لدينا", "es": "Conecta con Nuestro Equipo de Ventas y Soporte", "fr": "Contactez notre équipe commerciale et support",
        "pt": "Fale com Nossa Equipe de Vendas e Suporte", "de": "Verbinden Sie sich mit unserem Vertriebs- & Supportteam", "zh": "随时联系我们的业务顾问与技术支持团队", "ja": "セールス＆サポートチームにお気軽にご相談ください",
        "ru": "Свяжитесь с нашей службой продаж и поддержки", "it": "Contatta il nostro team di vendita e assistenza", "id": "Hubungi Tim Penjualan & Dukungan Kami", "tr": "Satış ve Destek Ekibimizle İletişime Geçin"
    },
    "Have questions before signing up? Send us a message and our team will get in touch.": {
        "hi": "साइन अप करने से पहले प्रश्न हैं? हमें एक संदेश भेजें और हमारी टीम आपसे संपर्क करेगी।", "ar": "هل لديك استفسارات قبل التسجيل؟ أرسل لنا رسالة وسيتواصل فريقنا معك فوراً.", "es": "¿Tienes preguntas antes de registrarte? Envíanos un mensaje y nuestro equipo se comunicará contigo.", "fr": "Vous avez des questions avant de vous inscrire ? Envoyez-nous un message et notre équipe vous recontactera.",
        "pt": "Tem dúvidas antes de se cadastrar? Envie-nos uma mensagem e entraremos em contato.", "de": "Haben Sie Fragen vor der Registrierung? Senden Sie uns eine Nachricht, unser Team meldet sich umgehend.", "zh": "注册或采购前有任何疑问？欢迎随时发送留言，我们将在第一时间给您回电解答。", "ja": "ご登録前にご質問がございましたら、メッセージをお送りください。担当者より迅速にご案内いたします。",
        "ru": "Остались вопросы перед подключением? Напишите нам, и наша команда сразу свяжется с вами.", "it": "Hai dubbi prima di registrarti? Inviaci un messaggio e il nostro team ti ricontatterà al più presto.", "id": "Ada pertanyaan sebelum mendaftar? Kirimkan pesan kepada kami dan tim kami akan segera menghubungi Anda.", "tr": "Kaydolmadan önce sorularınız mı var? Bize bir mesaj gönderin, ekibimiz sizinle iletişime geçsin."
    },
    "Cloud & Offline Enterprise POS Architecture.": {
        "hi": "क्लाउड और ऑफ़लाइन एंटरप्राइज पीओएस आर्किटेक्चर।", "ar": "بنية نقاط بيع سحابية وتعمل بدون إنترنت للمؤسسات.", "es": "Arquitectura POS Empresarial en la Nube y Fuera de Línea.", "fr": "Architecture POS d'entreprise sur le cloud et hors ligne.",
        "pt": "Arquitetura de PDV Corporativo na Nuvem e Offline.", "de": "Cloud- und Offline-POS-Unternehmensarchitektur.", "zh": "高可用云端与完全离线企业级 POS 架构。", "ja": "クラウド＆オフライン対応 エンタープライズPOSアーキテクチャ。",
        "ru": "Облачная и офлайн POS-архитектура корпоративного уровня.", "it": "Architettura POS Aziendale Cloud e Offline.", "id": "Arsitektur POS Perusahaan Berbasis Cloud & Offline.", "tr": "Bulut ve Çevrimdışı Kurumsal POS Mimarisi."
    },
    "Metrotech Center, NY 11201": {
        "hi": "मेट्रोटेक सेंटर, न्यूयॉर्क 11201", "ar": "مركز ميتروتك، نيويورك 11201", "es": "Metrotech Center, NY 11201", "fr": "Metrotech Center, NY 11201",
        "pt": "Metrotech Center, NY 11201", "de": "Metrotech Center, NY 11201", "zh": "纽约大都会科技中心 11201", "ja": "Metrotech Center, NY 11201",
        "ru": "Метротек Центр, Нью-Йорк 11201", "it": "Metrotech Center, NY 11201", "id": "Metrotech Center, NY 11201", "tr": "Metrotech Center, NY 11201"
    },
    "Monday - Friday (07 am - 05 pm)": {
        "hi": "सोमवार - शुक्रवार (सुबह 07 बजे - शाम 05 बजे)", "ar": "الإثنين - الجمعة (07 صباحاً - 05 مساءً)", "es": "Lunes - Viernes (07 am - 05 pm)", "fr": "Lundi - Vendredi (07h00 - 17h00)",
        "pt": "Segunda - Sexta (07h às 17h)", "de": "Montag - Freitag (07:00 - 17:00 Uhr)", "zh": "周一至周五 (上午 07:00 - 下午 05:00)", "ja": "月曜日 - 金曜日 (午前7時 - 午後5時)",
        "ru": "Понедельник - Пятница (с 07:00 до 17:00)", "it": "Lunedì - Venerdì (07:00 - 17:00)", "id": "Senin - Jumat (07.00 - 17.00)", "tr": "Pazartesi - Cuma (07:00 - 17:00)"
    }
}

# 1. Update lang/*.json
for loc in langs:
    filepath = os.path.join(lang_dir, f"{loc}.json")
    data = {}
    if os.path.exists(filepath):
        with open(filepath, 'r', encoding='utf-8') as f:
            try:
                data = json.load(f)
            except Exception:
                data = {}
    
    count = 0
    for k, v in remaining_keys.items():
        if loc in v:
            data[k] = v[loc]
            count += 1
            
    with open(filepath, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)
    print(f"Updated {loc}.json with {count} remaining keys.")

# 2. Re-read all lang files to build complete catalog
full_dict = {}
for loc in langs:
    filepath = os.path.join(lang_dir, f"{loc}.json")
    if os.path.exists(filepath):
        with open(filepath, 'r', encoding='utf-8') as f:
            try:
                d = json.load(f)
                for k, v in d.items():
                    if k not in full_dict:
                        full_dict[k] = {}
                    full_dict[k][loc] = v
            except Exception as e:
                print(f"Error reading {filepath}: {e}")

out_path = os.path.join(base_dir, 'mobile', 'lib', 'features', 'landing', 'models', 'landing_translations.dart')
with open(out_path, 'w', encoding='utf-8') as f:
    f.write("""// Generated bundled translations for Zoom Sales POS Landing Page
// Supports zero-latency switching with fallback to Laravel API & English originals.
import 'package:zoom_pos_mobile/core/services/translations_cache.dart';

class LandingTranslations {
  LandingTranslations._();

  static String tr(String text, [String? localeCode]) {
    final code = (localeCode ?? 'en').toLowerCase().trim();
    if (code.isEmpty || code == 'en') {
      return text;
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
                val = v[loc].replace('\\', '\\\\').replace("'", "\\'").replace('$', '\\$').replace('\r', '').replace('\n', '\\n')
                key_escaped = k.replace('\\', '\\\\').replace("'", "\\'").replace('$', '\\$').replace('\r', '').replace('\n', '\\n')
                f.write(f"      '{key_escaped}': '{val}',\n")
        f.write("    },\n")
    f.write("""  };
}
""")

print(f"Successfully generated {out_path} with {len(full_dict)} total keys!")
