<?php
session_start();

// Connexion à la base de données
try {
    $pdo = new PDO("mysql:host=localhost;dbname=e_boutique", "root", "rootroot");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Vérifier si le panier existe
if (!isset($_SESSION['panier']) || !is_array($_SESSION['panier'])) {
    $_SESSION['panier'] = [];
}

// Récupérer les catégories
$stmt = $pdo->query("SELECT * FROM categories");
$categories = $stmt->fetchAll();

// Filtrer les catégories pour ne pas afficher Homme et Femme
$categories_filtrees = array_filter($categories, function($category) {
    return $category['nom'] !== 'Homme' && $category['nom'] !== 'Femme';
});

// Récupérer la requête de recherche
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Récupérer les filtres de recherche avancée
$categorie_id = isset($_GET['categorie_id']) ? intval($_GET['categorie_id']) : null;
$prix_min = isset($_GET['prix_min']) && is_numeric($_GET['prix_min']) ? floatval($_GET['prix_min']) : null;
$prix_max = isset($_GET['prix_max']) && is_numeric($_GET['prix_max']) ? floatval($_GET['prix_max']) : null;

// Requête de base pour récupérer les produits
$query = "SELECT p.*, c.nom AS categorie_nom FROM products p 
          LEFT JOIN categories c ON p.categorie_id = c.id 
          WHERE 1=1";
$params = [];

// Ajouter le filtre de status si la colonne existe (vérifier dans la BD)
try {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM products LIKE 'status'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        $query .= " AND (p.status = 'disponible' OR p.status IS NULL)";
    }
} catch (PDOException $e) {
    // Ignorer silencieusement si la colonne n'existe pas
}

// Ajouter la condition de recherche si une requête est présente
if (!empty($search)) {
    $query .= " AND (p.titre LIKE :search OR p.description LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

// Ajouter la condition de catégorie si une catégorie est sélectionnée
if ($categorie_id) {
    $query .= " AND p.categorie_id = :categorie_id";
    $params['categorie_id'] = $categorie_id;
}

// Ajouter des filtres de prix
if ($prix_min !== null) {
    $query .= " AND p.prix >= :prix_min";
    $params['prix_min'] = $prix_min;
}

if ($prix_max !== null) {
    $query .= " AND p.prix <= :prix_max";
    $params['prix_max'] = $prix_max;
}

// Ajouter tri par date de publication (du plus récent au plus ancien)
$query .= " ORDER BY p.date_publication DESC";

// Préparer et exécuter la requête
$stmt = $pdo->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$produits = $stmt->fetchAll();

// Gestion de l'ajout au panier
if (isset($_POST['ajouter_panier'])) {
    $id_produit = intval($_POST['id_produit']);

    // Vérifie si le produit est déjà dans le panier
    if (isset($_SESSION['panier'][$id_produit])) {
        $_SESSION['panier'][$id_produit] += 1; // Incrémente la quantité
    } else {
        $_SESSION['panier'][$id_produit] = 1; // Ajoute le produit avec une quantité de 1
    }

    // Rediriger pour éviter de renvoyer le formulaire lors d'un rafraîchissement
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// Récupérer le prix minimum et maximum dans la base de données pour la recherche avancée
$stmt = $pdo->query("SELECT MIN(prix) as min_prix, MAX(prix) as max_prix FROM products");
$prix_range = $stmt->fetch();
$db_prix_min = $prix_range['min_prix'] ?? 0;
$db_prix_max = $prix_range['max_prix'] ?? 1000;

// Pour le formulaire, utiliser les valeurs de filtrage actuelles ou les valeurs par défaut
$form_prix_min = $prix_min ?? $db_prix_min;
$form_prix_max = $prix_max ?? $db_prix_max;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BANDER-SHOP</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #FF8C00; /* Orange pour les accents */
            --secondary-color: #333333; /* Gris foncé pour le texte principal */
            --background-color: #FFFFFF; /* Fond blanc pur */
            --light-gray: #F8F8F8; /* Gris très clair pour les fonds secondaires */
            --border-color: #EDEDED; /* Bordure subtile */
            --text-color: #555555; /* Texte doux */
            --hover-color: #FF8C00; /* Même orange pour survol */
            --transition-speed: 0.3s;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            --radius: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
            font-size: 16px;
        }

        /* Notification */
        .notification {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: var(--radius);
            background-color: #E3F2FD;
            color: #0D47A1;
            box-shadow: var(--shadow);
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            animation: slideIn 0.3s ease-out forwards;
            max-width: 350px;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Header */
        header {
            background-color: var(--background-color);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 15px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo a {
            text-decoration: none;
            color: var(--secondary-color);
            font-weight: 700;
            font-size: 28px;
            letter-spacing: 1px;
            transition: color var(--transition-speed);
            display: flex;
            align-items: center;
        }

        .logo a:hover {
            color: var(--hover-color);
        }

        .logo a::before {
            content: "🛍️";
            margin-right: 10px;
            font-size: 24px;
        }

        .search-container {
            flex-grow: 1;
            max-width: 600px;
            margin: 0 40px;
            position: relative;
        }

        .search-bar {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-bar input {
            width: 100%;
            padding: 14px 20px;
            border: 1px solid var(--border-color);
            border-radius: 50px;
            font-size: 15px;
            background-color: var(--light-gray);
            transition: all var(--transition-speed);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 140, 0, 0.1);
        }

        .search-bar button {
            position: absolute;
            right: 15px;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--primary-color);
            font-size: 18px;
        }

        .advanced-search-toggle {
            background-color: transparent;
            border: none;
            padding: 10px 15px;
            margin-left: 15px;
            cursor: pointer;
            font-size: 14px;
            color: var(--text-color);
            transition: color var(--transition-speed);
            display: flex;
            align-items: center;
            border-radius: 20px;
        }

        .advanced-search-toggle:hover {
            color: var(--primary-color);
            background-color: rgba(255, 140, 0, 0.05);
        }

        .advanced-search-toggle i {
            margin-right: 5px;
        }

        .advanced-search-panel {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background-color: white;
            padding: 20px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-top: 10px;
            z-index: 10;
        }

        .advanced-search-panel.show {
            display: block;
            animation: fadeIn 0.2s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .advanced-search-panel h4 {
            margin-bottom: 15px;
            color: var(--secondary-color);
            font-size: 16px;
            font-weight: 600;
        }

        .filter-group {
            margin-bottom: 20px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--secondary-color);
            font-weight: 500;
        }

        .filter-group select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background-color: var(--light-gray);
            transition: border-color var(--transition-speed);
        }

        .filter-group select:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .price-range-container {
            padding: 10px 0;
        }

        .price-display {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
            color: var(--secondary-color);
        }

        .price-slider {
            position: relative;
            height: 30px;
        }

        .price-slider input[type="range"] {
            position: absolute;
            width: 100%;
            background: transparent;
            -webkit-appearance: none;
        }

        .price-slider input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            height: 20px;
            width: 20px;
            border-radius: 50%;
            background: var(--primary-color);
            cursor: pointer;
            margin-top: -8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .price-slider input[type="range"]::-webkit-slider-runnable-track {
            width: 100%;
            height: 4px;
            background: #ddd;
            border-radius: 2px;
        }

        .advanced-search-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        .reset-filter, .apply-filter {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-speed);
        }

        .reset-filter {
            background-color: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-color);
        }

        .reset-filter:hover {
            background-color: var(--light-gray);
        }

        .apply-filter {
            background-color: var(--primary-color);
            border: none;
            color: white;
        }

        .apply-filter:hover {
            background-color: #E07B00;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .header-actions a {
            text-decoration: none;
            color: var(--text-color);
            font-size: 14px;
            font-weight: 500;
            transition: color var(--transition-speed);
            display: flex;
            align-items: center;
        }

        .header-actions a i {
            margin-right: 6px;
            font-size: 16px;
        }

        .header-actions a:hover {
            color: var(--primary-color);
        }

        .sell-button {
            background-color: var(--primary-color);
            color: white !important;
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: 600;
            transition: all var(--transition-speed);
            box-shadow: 0 4px 10px rgba(255, 140, 0, 0.2);
        }

        .sell-button:hover {
            background-color: #E07B00;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 140, 0, 0.25);
        }

        /* Navigation */
        .category-nav {
            background-color: var(--background-color);
            border-bottom: 1px solid var(--border-color);
            padding: 0;
        }

        .category-nav ul {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 30px;
            display: flex;
            list-style: none;
            gap: 10px;
            overflow-x: auto;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }

        .category-nav ul::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }

        .category-nav li {
            flex-shrink: 0;
        }

        .category-nav a {
            text-decoration: none;
            color: var(--text-color);
            font-size: 14px;
            font-weight: 500;
            padding: 15px 20px;
            display: block;
            transition: all var(--transition-speed);
            border-bottom: 2px solid transparent;
        }

        .category-nav a:hover {
            color: var(--primary-color);
        }

        .category-nav a.active {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
        }

        /* Hero Banner */
        .hero-banner {
            background-color: var(--light-gray);
            padding: 60px 30px;
            margin-bottom: 50px;
            position: relative;
            overflow: hidden;
        }

        .hero-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 40px;
        }

        .hero-text {
            flex: 1;
            max-width: 600px;
        }

        .hero-text h1 {
            font-size: 42px;
            color: var(--secondary-color);
            font-weight: 700;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .hero-text p {
            font-size: 18px;
            color: var(--text-color);
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .cta-button {
            background-color: var(--primary-color);
            color: white;
            padding: 14px 32px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all var(--transition-speed);
            display: inline-block;
            box-shadow: 0 4px 15px rgba(255, 140, 0, 0.2);
        }

        .cta-button:hover {
            background-color: #E07B00;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 140, 0, 0.25);
        }

        .hero-image {
            flex: 1;
            max-width: 600px;
        }

        .hero-image img {
            width: 100%;
            height: auto;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            transition: transform 0.5s ease;
        }

        .hero-image img:hover {
            transform: scale(1.02);
        }

        /* Main Content */
        main {
            max-width: 1400px;
            margin: 0 auto 60px;
            padding: 0 30px;
        }

        .section-title {
            font-size: 28px;
            color: var(--secondary-color);
            font-weight: 600;
            margin-bottom: 30px;
            position: relative;
            padding-bottom: 10px;
        }

        .section-title::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background-color: var(--primary-color);
            border-radius: 3px;
        }

        .product-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
        }

        .product-card {
            background-color: var(--background-color);
            border-radius: var(--radius);
            overflow: hidden;
            transition: all var(--transition-speed);
            box-shadow: var(--shadow);
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
        }

        .product-card img {
            width: 100%;
            height: 220px;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .product-card:hover img {
            transform: scale(1.05);
        }

        .product-card-content {
            padding: 20px;
        }

        .product-card a {
            text-decoration: none;
        }

        .product-card h3 {
            font-size: 18px;
            color: var(--secondary-color);
            font-weight: 600;
            margin-bottom: 8px;
            transition: color var(--transition-speed);
        }

        .product-card:hover h3 {
            color: var(--primary-color);
        }

        .product-card p {
            font-size: 14px;
            color: var(--text-color);
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .price {
            font-size: 18px;
            color: var(--primary-color);
            font-weight: 700;
            display: flex;
            align-items: center;
        }

        .price::before {
            content: "€";
            margin-right: 2px;
            font-size: 14px;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background-color: var(--light-gray);
            border-radius: var(--radius);
        }

        .empty-state i {
            font-size: 48px;
            color: #ccc;
            margin-bottom: 20px;
        }

        .empty-state p {
            font-size: 18px;
            color: var(--text-color);
            margin-bottom: 20px;
        }

        /* Footer */
        footer {
            background-color: var(--light-gray);
            padding: 60px 30px 30px;
            margin-top: 80px;
            border-top: 1px solid var(--border-color);
        }

        .footer-container {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
        }

        .footer-column h4 {
            font-size: 18px;
            color: var(--secondary-color);
            font-weight: 600;
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 10px;
        }

        .footer-column h4::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            width: 30px;
            height: 2px;
            background-color: var(--primary-color);
        }

        .footer-column ul {
            list-style: none;
        }

        .footer-column li {
            margin-bottom: 10px;
        }

        .footer-column a {
            text-decoration: none;
            color: var(--text-color);
            font-size: 14px;
            transition: color var(--transition-speed);
            display: inline-block;
        }

        .footer-column a:hover {
            color: var(--primary-color);
            transform: translateX(3px);
        }

        .copyright {
            text-align: center;
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            font-size: 14px;
            color: var(--text-color);
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .hero-text h1 {
                font-size: 36px;
            }
        }

        @media (max-width: 992px) {
            .hero-content {
                flex-direction: column;
                text-align: center;
            }
            
            .hero-text {
                max-width: 100%;
            }
            
            .hero-text h1 {
                font-size: 32px;
            }
            
            .section-title::after {
                left: 50%;
                transform: translateX(-50%);
            }
            
            .section-title {
                text-align: center;
            }
        }

        @media (max-width: 768px) {
            .header-container {
                flex-wrap: wrap;
                gap: 20px;
            }
            
            .search-container {
                order: 3;
                max-width: 100%;
                margin: 10px 0 0;
                width: 100%;
            }
            
            .header-actions {
                gap: 15px;
            }
            
            .product-container {
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            }
            
            .footer-container {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }

        @media (max-width: 576px) {
            .header-container {
                padding: 15px;
            }
            
            .logo a {
                font-size: 24px;
            }
            
            .header-actions {
                gap: 10px;
            }
            
            .header-actions a {
                font-size: 12px;
            }
            
            .sell-button {
                padding: 10px 15px;
                font-size: 12px;
            }
            
            .hero-banner {
                padding: 40px 15px;
            }
            
            .hero-text h1 {
                font-size: 28px;
            }
            
            .hero-text p {
                font-size: 16px;
            }
            
            .cta-button {
                padding: 12px 25px;
                font-size: 14px;
            }
            
            main {
                padding: 0 15px;
            }
            
            .section-title {
                font-size: 24px;
            }
            
            .product-container {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 15px;
            }
            
            .product-card-content {
                padding: 15px;
            }
            
            .product-card h3 {
                font-size: 16px;
            }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle affichage du panneau de recherche avancée
            const toggleButton = document.getElementById('advanced-search-toggle');
            const advancedPanel = document.getElementById('advanced-search-panel');
            
            if (toggleButton && advancedPanel) {
                toggleButton.addEventListener('click', function(event) {
                    event.stopPropagation();
                    advancedPanel.classList.toggle('show');
                });
                
                // Fermer le panneau si on clique en dehors
                document.addEventListener('click', function(event) {
                    if (!advancedPanel.contains(event.target) && !toggleButton.contains(event.target)) {
                        advancedPanel.classList.remove('show');
                    }
                });
                
                // Empêcher la fermeture quand on clique dans le panneau
                advancedPanel.addEventListener('click', function(event) {
                    event.stopPropagation();
                });
            }
            
            // Mise à jour dynamique des valeurs du slider de prix
            const minPriceInput = document.getElementById('prix_min');
            const maxPriceInput = document.getElementById('prix_max');
            const minPriceDisplay = document.getElementById('min-price-display');
            const maxPriceDisplay = document.getElementById('max-price-display');
            
            if (minPriceInput && minPriceDisplay) {
                minPriceInput.addEventListener('input', function() {
                    minPriceDisplay.textContent = parseFloat(this.value).toFixed(2) + ' €';
                    
                    // S'assurer que min <= max
                    if (parseFloat(this.value) > parseFloat(maxPriceInput.value)) {
                        maxPriceInput.value = this.value;
                        maxPriceDisplay.textContent = parseFloat(this.value).toFixed(2) + ' €';
                    }
                });
            }
            
            if (maxPriceInput && maxPriceDisplay) {
                maxPriceInput.addEventListener('input', function() {
                    maxPriceDisplay.textContent = parseFloat(this.value).toFixed(2) + ' €';
                    
                    // S'assurer que max >= min
                    if (parseFloat(this.value) < parseFloat(minPriceInput.value)) {
                        minPriceInput.value = this.value;
                        minPriceDisplay.textContent = parseFloat(this.value).toFixed(2) + ' €';
                    }
                });
            }
            
            // Auto-hide notifications after 5 seconds
            const notifications = document.querySelectorAll('.notification');
            if (notifications.length > 0) {
                setTimeout(() => {
                    notifications.forEach(notification => {
                        notification.style.animation = 'slideOut 0.3s ease-in forwards';
                    });
                }, 5000);
            }
        });
        
        // Animation for product cards on scroll
        window.addEventListener('scroll', function() {
            const productCards = document.querySelectorAll('.product-card');
            productCards.forEach(card => {
                const cardPosition = card.getBoundingClientRect().top;
                const screenPosition = window.innerHeight / 1.2;
                
                if (cardPosition < screenPosition) {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }
            });
        });
    </script>
</head>
<body>

<?php if (isset($_SESSION['message'])): ?>
    <div class="notification">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['message']) ?>
        <?php unset($_SESSION['message']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['erreur'])): ?>
    <div class="notification" style="background-color: #FFE8E6; color: #D8000C;">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['erreur']) ?>
        <?php unset($_SESSION['erreur']); ?>
    </div>
<?php endif; ?>

<header>
    <div class="header-container">
        <div class="logo">
            <a href="index.php">BANDER-SHOP</a>
        </div>

        <div class="search-container">
            <form class="search-bar" action="index.php" method="get">
                <input type="text" placeholder="Rechercher des articles..." name="search" value="<?= htmlspecialchars($search ?? '') ?>">
                <button type="submit"><i class="fas fa-search"></i></button>
                <button type="button" id="advanced-search-toggle" class="advanced-search-toggle">
                    <i class="fas fa-sliders-h"></i> Filtres
                </button>
                
                <!-- Panneau de recherche avancée -->
                <div id="advanced-search-panel" class="advanced-search-panel">
                    <h4>Filtres de recherche</h4>
                    
                    <div class="filter-group">
                        <label for="categorie_id">Catégorie</label>
                        <select name="categorie_id" id="categorie_id">
                            <option value="">Toutes les catégories</option>
                            <?php foreach ($categories_filtrees as $category): ?>
                                <option value="<?= $category['id'] ?>" <?= $categorie_id == $category['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Fourchette de prix</label>
                        <div class="price-range-container">
                            <div class="price-range">
                                <div class="price-display">
                                    <span id="min-price-display"><?= number_format($form_prix_min, 2) ?> €</span>
                                    <span id="max-price-display"><?= number_format($form_prix_max, 2) ?> €</span>
                                </div>
                                <div class="price-slider">
                                    <input type="range" name="prix_min" id="prix_min" min="<?= $db_prix_min ?>" max="<?= $db_prix_max ?>" step="1" value="<?= $form_prix_min ?>">
                                    <input type="range" name="prix_max" id="prix_max" min="<?= $db_prix_min ?>" max="<?= $db_prix_max ?>" step="1" value="<?= $form_prix_max ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="advanced-search-actions">
                        <button type="button" class="reset-filter" onclick="window.location.href='index.php'">Réinitialiser</button>
                        <button type="submit" class="apply-filter">Appliquer</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="header-actions">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="compte.php"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['user_nom'] ?? 'Mon compte'); ?></a>
                <a href="messages.php"><i class="fas fa-envelope"></i> Messages</a>
                <a href="panier.php"><i class="fas fa-shopping-cart"></i> Panier (<?= array_sum($_SESSION['panier']) ?>)</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
            <?php else: ?>
                <a href="connexion.php"><i class="fas fa-sign-in-alt"></i> Se connecter</a>
                <a href="inscription.php"><i class="fas fa-user-plus"></i> S'inscrire</a>
                <a href="panier.php"><i class="fas fa-shopping-cart"></i> Panier (<?= array_sum($_SESSION['panier']) ?>)</a>
            <?php endif; ?>
            <a href="<?= isset($_SESSION['user_id']) ? 'mes_articles.php' : 'connexion.php' ?>" class="sell-button">
                <i class="fas fa-tag"></i> Vends tes articles
            </a>
        </div>
    </div>

    <nav class="category-nav">
        <ul>
            <li><a href="index.php" class="<?= !isset($_GET['categorie_id']) ? 'active' : '' ?>">Tous les produits</a></li>
            <?php
            foreach ($categories_filtrees as $category):
                $isActive = (isset($_GET['categorie_id']) && $_GET['categorie_id'] == $category['id']);
            ?>
                <li>
                    <a href="?categorie_id=<?= $category['id'] ?>" class="<?= $isActive ? 'active' : '' ?>">
                        <?= htmlspecialchars($category['nom']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1): ?>
    <a href="admin.php" class="admin-link"><i class="fas fa-tachometer-alt"></i> Administration</a>
<?php endif; ?>
</header>

<section class="hero-banner">
    <div class="hero-content">
        <div class="hero-text">
            <h1>Prêts à faire du tri dans vos placards ?</h1>
            <p>Achetez et vendez des vêtements, chaussures et accessoires de seconde main. Donnez une seconde vie à vos articles préférés.</p>
            <a href="<?= isset($_SESSION['user_id']) ? 'mes_articles.php' : 'inscription.php' ?>" class="cta-button">
                <i class="fas fa-tag"></i> Vendre maintenant
            </a>
        </div>
        <div class="hero-image">
            <img src="banner.jpg"alt=am>
        </div>
    </div>
</section>

<main>
    <h2 class="section-title">
        <?php
        if (!empty($search)) {
            echo 'Résultats pour "' . htmlspecialchars($search) . '"';
        } elseif ($categorie_id) {
            foreach ($categories as $category) {
                if ($category['id'] == $categorie_id) {
                    echo htmlspecialchars($category['nom']);
                    break;
                }
            }
        } else {
            echo 'Tous les produits';
        }
        
        // Affiche info sur les filtres
        if ($prix_min !== null || $prix_max !== null) {
            echo ' - Prix: ';
            if ($prix_min !== null) {
                echo 'min. ' . number_format($prix_min, 2) . '€';
            }
            if ($prix_min !== null && $prix_max !== null) {
                echo ' - ';
            }
            if ($prix_max !== null) {
                echo 'max. ' . number_format($prix_max, 2) . '€';
            }
        }
        ?>
    </h2>

    <section class="product-container">
        <?php if (empty($produits)): ?>
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <p>Aucun produit trouvé pour votre recherche.</p>
                <a href="index.php" class="cta-button">Voir tous les produits</a>
            </div>
        <?php else: ?>
            <?php foreach ($produits as $produit): ?>
                <div class="product-card" style="opacity: 0; transform: translateY(20px); transition: opacity 0.5s ease, transform 0.5s ease;">
                    <a href="produit.php?id=<?= $produit['id'] ?>">
                        <img src="<?= htmlspecialchars($produit['image_url']) ?>" alt="<?= htmlspecialchars($produit['titre']) ?>">
                    </a>
                    <div class="product-card-content">
                        <a href="produit.php?id=<?= $produit['id'] ?>">
                            <h3><?= htmlspecialchars($produit['titre']) ?></h3>
                            <p><?= htmlspecialchars(substr($produit['description'], 0, 50)) ?>...</p>
                        </a>
                        <p class="price"><?= $produit['prix'] ?></p>
                        <form action="index.php" method="post" style="margin-top: 10px;">
                            <input type="hidden" name="id_produit" value="<?= $produit['id'] ?>">
                            <button type="submit" name="ajouter_panier" style="background-color: var(--primary-color); color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; width: 100%; font-size: 14px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-cart-plus" style="margin-right: 5px;"></i> Ajouter au panier
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>

<footer>
    <div class="footer-container">
        <div class="footer-column">
            <h4>BANDER-SHOP</h4>
            <ul>
                <li><a href="#"><i class="fas fa-info-circle"></i> À propos</a></li>
                <li><a href="#"><i class="fas fa-question-circle"></i> Comment ça marche</a></li>
                <li><a href="#"><i class="fas fa-shield-alt"></i> Confiance et sécurité</a></li>
                <li><a href="#"><i class="fas fa-newspaper"></i> Blog</a></li>
            </ul>
        </div>

        <div class="footer-column">
            <h4>Découvrir</h4>
            <ul>
                <li><a href="#"><i class="fas fa-mobile-alt"></i> Applications mobiles</a></li>
                <li><a href="#"><i class="fas fa-chart-line"></i> Tableau de bord</a></li>
                <li><a href="#"><i class="fas fa-star"></i> Produits populaires</a></li>
                <li><a href="#"><i class="fas fa-tags"></i> Offres spéciales</a></li>
            </ul>
        </div>

        <div class="footer-column">
            <h4>Aide</h4>
            <ul>
                <li><a href="#"><i class="fas fa-headset"></i> Centre d'aide</a></li>
                <li><a href="#"><i class="fas fa-tag"></i> Vendre</a></li>
                <li><a href="#"><i class="fas fa-shopping-bag"></i> Acheter</a></li>
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <li><a href="inscription.php"><i class="fas fa-user-plus"></i> Inscrivez-vous</a></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="footer-column">
            <h4>Suivez-nous</h4>
            <ul>
                <li><a href="#"><i class="fab fa-facebook"></i> Facebook</a></li>
                <li><a href="#"><i class="fab fa-instagram"></i> Instagram</a></li>
                <li><a href="#"><i class="fab fa-twitter"></i> Twitter</a></li>
                <li><a href="#"><i class="fab fa-pinterest"></i> Pinterest</a></li>
            </ul>
        </div>
    </div>

    <div class="copyright">
        <p>&copy; 2025 BANDER-SHOP - Tous droits réservés.</p>
    </div>
</footer>

</body>
</html>