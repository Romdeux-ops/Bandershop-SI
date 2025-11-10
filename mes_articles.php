<?php
session_start();

// Connexion à la base de données
try {
    $pdo = new PDO("mysql:host=localhost;dbname=e_boutique", "root", "rootroot");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer les catégories pour le menu de navigation
$stmt = $pdo->query("SELECT * FROM categories");
$categories = $stmt->fetchAll();

// Filtrer les catégories pour ne pas afficher Homme et Femme
$categories_filtrees = array_filter($categories, function($category) {
    return $category['nom'] !== 'Homme' && $category['nom'] !== 'Femme';
});

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupérer les produits de l'utilisateur
$stmt = $pdo->prepare("SELECT p.*, c.nom AS categorie_nom FROM products p
                       LEFT JOIN categories c ON p.categorie_id = c.id
                       WHERE p.vendeur_id = ?");
$stmt->execute([$user_id]);
$produits = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Articles - BANDER-SHOP</title>
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
            --error-color: #e74c3c;
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

        /* Main content */
        main {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 30px;
        }

        .page-title {
            font-size: 32px;
            margin-bottom: 30px;
            color: var(--secondary-color);
            text-align: center;
            font-weight: 700;
        }

        .add-product-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: var(--primary-color);
            color: white;
            padding: 14px 28px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 40px;
            transition: all var(--transition-speed);
            box-shadow: 0 4px 10px rgba(255, 140, 0, 0.2);
            font-size: 16px;
        }

        .add-product-button i {
            margin-right: 8px;
            font-size: 18px;
        }

        .add-product-button:hover {
            background-color: #E07B00;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 140, 0, 0.25);
        }

        .products-container {
            background-color: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 40px;
            margin-bottom: 40px;
        }

        .empty-message {
            text-align: center;
            padding: 60px 20px;
            color: #777;
            font-size: 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .empty-message i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-message p {
            margin-bottom: 25px;
            max-width: 500px;
        }

        /* Products grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
        }

        .product-card {
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all var(--transition-speed);
            background-color: white;
            position: relative;
            border: 1px solid var(--border-color);
        }

        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.08);
            border-color: var(--primary-color);
        }

        .product-image {
            width: 100%;
            height: 220px;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .product-card:hover .product-image {
            transform: scale(1.05);
        }

        .product-content {
            padding: 20px;
        }

        .product-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--secondary-color);
            line-height: 1.3;
        }

        .product-category {
            font-size: 13px;
            color: #777;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }

        .product-category i {
            margin-right: 5px;
            color: var(--primary-color);
        }

        .product-description {
            font-size: 14px;
            color: var(--text-color);
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.5;
        }

        .product-price {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .product-price::before {
            content: "€";
            margin-right: 2px;
            font-size: 16px;
        }

        .product-actions {
            display: flex;
            gap: 10px;
        }

        .edit-button, .delete-button {
            flex: 1;
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-align: center;
            font-size: 14px;
            transition: all var(--transition-speed);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .edit-button {
            background-color: var(--light-gray);
            color: var(--secondary-color);
            border: 1px solid var(--border-color);
        }

        .edit-button i {
            margin-right: 5px;
        }

        .edit-button:hover {
            background-color: var(--border-color);
            color: var(--primary-color);
        }

        .delete-button {
            background-color: #FFF5F5;
            color: var(--error-color);
            border: 1px solid #FFDDDD;
        }

        .delete-button i {
            margin-right: 5px;
        }

        .delete-button:hover {
            background-color: #FFE8E8;
        }

        /* Status badge */
        .status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            z-index: 1;
        }

        .status-disponible {
            background-color: #E3F9E5;
            color: #1B873F;
        }

        .status-vendu {
            background-color: #FFF0E0;
            color: #FF8C00;
        }

        .status-reserve {
            background-color: #E3F2FD;
            color: #0D47A1;
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
            transition: all var(--transition-speed);
            display: flex;
            align-items: center;
        }

        .footer-column a i {
            margin-right: 8px;
            color: var(--primary-color);
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
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
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
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
                gap: 20px;
            }
            
            .products-container {
                padding: 30px 20px;
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
            
            main {
                padding: 0 15px;
            }
            
            .page-title {
                font-size: 24px;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .product-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<header>
    <div class="header-container">
        <div class="logo">
            <a href="index.php">BANDER-SHOP</a>
        </div>

        <div class="search-container">
            <form class="search-bar" action="index.php" method="get">
                <input type="text" placeholder="Rechercher des articles..." name="search">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>

        <div class="header-actions">
            <a href="compte.php"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['user_nom']); ?></a>
            <a href="messages.php"><i class="fas fa-envelope"></i> Messages</a>
            <a href="mes_articles.php"><i class="fas fa-box"></i> <strong>Mes Articles</strong></a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </div>
    </div>

    <nav class="category-nav">
        <ul>
            <li><a href="index.php">Tous les produits</a></li>
            <?php foreach ($categories_filtrees as $category): ?>
                <li>
                    <a href="index.php?categorie_id=<?= $category['id'] ?>">
                        <?= htmlspecialchars($category['nom']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
</header>

<main>
    <h1 class="page-title">Mes Articles à Vendre</h1>

    <div style="text-align: center;">
        <a href="ajouter_produit.php" class="add-product-button">
            <i class="fas fa-plus-circle"></i> Ajouter un nouvel article
        </a>
    </div>

    <div class="products-container">
        <?php if (empty($produits)): ?>
            <div class="empty-message">
                <i class="fas fa-box-open"></i>
                <p>Vous n'avez aucun article à vendre pour le moment. Commencez à vendre en ajoutant un article!</p>
                <a href="ajouter_produit.php" class="add-product-button">
                    <i class="fas fa-plus-circle"></i> Ajouter mon premier article
                </a>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($produits as $produit): ?>
                    <div class="product-card">
                        <?php if (isset($produit['status'])): ?>
                            <div class="status-badge status-<?= strtolower($produit['status']) ?>">
                                <?= htmlspecialchars($produit['status']) ?>
                            </div>
                        <?php endif; ?>
                        <img src="<?= htmlspecialchars($produit['image_url']) ?>" alt="<?= htmlspecialchars($produit['titre']) ?>" class="product-image">
                        <div class="product-content">
                            <h3 class="product-title"><?= htmlspecialchars($produit['titre']) ?></h3>
                            <p class="product-category">
                                <i class="fas fa-tag"></i> <?= htmlspecialchars($produit['categorie_nom'] ?? 'Non catégorisé') ?>
                            </p>
                            <p class="product-description"><?= htmlspecialchars(substr($produit['description'], 0, 100)) ?>...</p>
                            <p class="product-price"><?= number_format($produit['prix'], 2, ',', ' ') ?></p>

                            <div class="product-actions">
                                <a href="modifier_produit.php?id=<?= $produit['id'] ?>" class="edit-button">
                                    <i class="fas fa-edit"></i> Modifier
                                </a>
                                <form action="supprimer_produit.php" method="POST" style="flex: 1;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet article ?');">
                                    <input type="hidden" name="id_produit" value="<?= $produit['id'] ?>">
                                    <button type="submit" class="delete-button">
                                        <i class="fas fa-trash-alt"></i> Supprimer
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<footer>
    <div class="footer-container">
        <div class="footer-column">
            <h4>BANDER-SHOP</h4>
            <ul>
                <li><a href="#"><i class="fas fa-info-circle"></i> À propos</a></li>
                <li><a href="#"><i class="fas fa-question-circle"></i> Comment ça marche</a></li>
                <li><a href="#"><i class="fas fa-shield-alt"></i> Confiance et sécurité</a></li>
            </ul>
        </div>

        <div class="footer-column">
            <h4>Découvrir</h4>
            <ul>
                <li><a href="#"><i class="fas fa-mobile-alt"></i> Applications mobiles</a></li>
                <li><a href="#"><i class="fas fa-chart-line"></i> Tableau de bord</a></li>
            </ul>
        </div>

        <div class="footer-column">
            <h4>Aide</h4>
            <ul>
                <li><a href="#"><i class="fas fa-headset"></i> Centre d'aide</a></li>
                <li><a href="#"><i class="fas fa-tag"></i> Vendre</a></li>
                <li><a href="#"><i class="fas fa-shopping-bag"></i> Acheter</a></li>
            </ul>
        </div>
    </div>

    <div class="copyright">
        <p>&copy; 2025 BANDER-SHOP - Tous droits réservés.</p>
    </div>
</footer>

</body>
</html>