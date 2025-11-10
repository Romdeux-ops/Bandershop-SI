<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

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

// Filtrer les catégories pour ne pas afficher "Homme" et "Femme"
$categories_filtrees = array_filter($categories, function($category) {
    return $category['nom'] !== 'Homme' && $category['nom'] !== 'Femme';
});

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Récupérer toutes les commandes de l'utilisateur
$stmt = $pdo->prepare("
    SELECT o.*, COUNT(op.product_id) AS nombre_produits 
    FROM orders o 
    LEFT JOIN order_products op ON o.id = op.order_id 
    WHERE o.acheteur_id = ? 
    GROUP BY o.id 
    ORDER BY o.date_commande DESC
");
$stmt->execute([$_SESSION['user_id']]);
$commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les détails d'une commande spécifique si un ID est fourni
$commande_details = null;
$produits_commande = [];
$etapes_commande = [];

if (isset($_GET['commande_id'])) {
    $commande_id = intval($_GET['commande_id']);

    // Vérifier que la commande appartient à l'utilisateur
    $stmt = $pdo->prepare("
        SELECT o.* 
        FROM orders o 
        WHERE o.id = ? AND o.acheteur_id = ?
    ");
    $stmt->execute([$commande_id, $_SESSION['user_id']]);
    $commande_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($commande_details) {
        // Récupérer les produits de la commande avec leurs prix unitaires
        $stmt = $pdo->prepare("
            SELECT op.*, p.titre, p.image_url, c.nom AS categorie_nom 
            FROM order_products op 
            LEFT JOIN products p ON op.product_id = p.id 
            LEFT JOIN categories c ON p.categorie_id = c.id 
            WHERE op.order_id = ?
        ");
        $stmt->execute([$commande_id]);
        $produits_commande = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Récupérer les étapes de la commande
        $stmt = $pdo->prepare("
            SELECT * 
            FROM order_steps 
            WHERE order_id = ? 
            ORDER BY date_etape ASC
        ");
        $stmt->execute([$commande_id]);
        $etapes_commande = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi de commandes - BANDER-SHOP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #FF8C00;
            --primary-dark: #E67E00;
            --secondary-color: #FFA500;
            --accent-color: #FFD700;
            --text-color: #333;
            --text-light: #777;
            --light-background: #FFF8E7;
            --border-color: #FFEFD5;
            --success-color: #4CAF50;
            --warning-color: #FF9800;
            --info-color: #2196F3;
            --cancel-color: #e74c3c;
            --card-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            --transition-speed: 0.3s;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f9f9f9;
            color: var(--text-color);
            line-height: 1.6;
        }

        header {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo a {
            text-decoration: none;
            color: var(--primary-color);
            font-weight: bold;
            font-size: 28px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: color var(--transition-speed);
        }

        .logo a:hover {
            color: var(--primary-dark);
        }

        .search-container {
            flex-grow: 1;
            max-width: 500px;
            margin: 0 20px;
        }

        .search-bar {
            position: relative;
            width: 100%;
        }

        .search-bar input {
            width: 100%;
            padding: 12px 40px 12px 15px;
            border: 1px solid #e9e9e9;
            border-radius: 8px;
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.03);
            transition: all var(--transition-speed);
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 3px 8px rgba(255, 140, 0, 0.1);
        }

        .search-bar button {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: var(--primary-color);
            transition: color var(--transition-speed);
        }

        .search-bar button:hover {
            color: var(--primary-dark);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .header-actions a {
            text-decoration: none;
            color: var(--text-color);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: color var(--transition-speed);
        }

        .header-actions a:hover {
            color: var(--primary-color);
        }

        .sell-button {
            background-color: var(--primary-color);
            color: white !important;
            padding: 10px 18px;
            border-radius: 6px;
            font-weight: bold;
            transition: background-color var(--transition-speed), transform var(--transition-speed);
            box-shadow: 0 3px 8px rgba(255, 140, 0, 0.2);
        }

        .sell-button:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }

        .category-nav {
            background-color: white;
            border-bottom: 1px solid #eee;
            overflow-x: auto;
            white-space: nowrap;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.03);
        }

        .category-nav::-webkit-scrollbar {
            display: none;
        }

        .category-nav ul {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            list-style: none;
        }

        .category-nav li {
            margin-right: 25px;
        }

        .category-nav a {
            display: inline-block;
            text-decoration: none;
            color: var(--text-color);
            padding: 14px 0;
            font-size: 14px;
            position: relative;
            font-weight: 500;
            transition: color var(--transition-speed);
        }

        .category-nav a:hover {
            color: var(--primary-color);
        }

        .category-nav a:after {
            content: '';
            position: absolute;
            width: 0;
            height: 3px;
            bottom: 0;
            left: 0;
            background-color: var(--primary-color);
            transition: width var(--transition-speed);
        }

        .category-nav a:hover:after {
            width: 100%;
        }

        main {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .page-title {
            font-size: 28px;
            margin-bottom: 30px;
            color: var(--text-color);
            text-align: center;
            position: relative;
            padding-bottom: 15px;
        }

        .page-title:after {
            content: '';
            position: absolute;
            width: 80px;
            height: 3px;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            background-color: var(--primary-color);
        }

        .orders-container {
            background-color: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 25px;
            margin-bottom: 30px;
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .orders-list {
            list-style: none;
        }

        .order-item {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            transition: background-color var(--transition-speed);
            border-radius: 8px;
        }

        .order-item:hover {
            background-color: #f9f9f9;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .order-info {
            flex: 1;
        }

        .order-number {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .order-number i {
            color: var(--primary-color);
        }

        .order-details {
            font-size: 14px;
            color: var(--text-light);
        }

        .order-status {
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 13px;
            text-transform: capitalize;
            font-weight: 500;
        }

        .order-status.en_preparation {
            background-color: rgba(255, 215, 0, 0.2);
            color: #B7950B;
        }

        .order-status.expediee {
            background-color: rgba(255, 165, 0, 0.2);
            color: #D35400;
        }

        .order-status.en_livraison {
            background-color: rgba(135, 206, 235, 0.2);
            color: #2E86C1;
        }

        .order-status.livree {
            background-color: rgba(76, 175, 80, 0.2);
            color: #27AE60;
        }

        .order-status.annulee {
            background-color: rgba(231, 76, 60, 0.2);
            color: #C0392B;
        }

        .order-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .order-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            padding: 8px 15px;
            border: 1px solid var(--primary-color);
            border-radius: 6px;
            transition: all var(--transition-speed);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .order-link:hover {
            background-color: var(--primary-color);
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(255, 140, 0, 0.2);
        }

        .cancel-button {
            color: var(--cancel-color);
            text-decoration: none;
            font-weight: 600;
            padding: 8px 15px;
            border: 1px solid var(--cancel-color);
            border-radius: 6px;
            transition: all var(--transition-speed);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .cancel-button:hover {
            background-color: var(--cancel-color);
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(231, 76, 60, 0.2);
        }

        .order-details-container {
            background-color: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 30px;
            animation: slideUp 0.5s ease-in-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .order-header {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .order-title {
            font-size: 22px;
            font-weight: bold;
            color: var(--text-color);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .order-title i {
            color: var(--primary-color);
        }

        .order-meta {
            font-size: 14px;
            color: var(--text-light);
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }

        .order-meta i {
            color: var(--primary-color);
        }

        .products-list {
            margin-bottom: 30px;
        }

        .products-list h3 {
            font-size: 18px;
            margin-bottom: 15px;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .products-list h3 i {
            color: var(--primary-color);
        }

        .product-item {
            display: flex;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: transform var(--transition-speed), box-shadow var(--transition-speed);
        }

        .product-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .product-item img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 6px;
            margin-right: 15px;
        }

        .product-info {
            flex: 1;
        }

        .product-name {
            font-weight: bold;
            font-size: 15px;
            margin-bottom: 5px;
        }

        .product-details {
            font-size: 13px;
            color: var(--text-light);
        }

        .product-price {
            text-align: right;
            font-weight: bold;
            font-size: 15px;
            color: var(--primary-color);
        }

        .order-total {
            text-align: right;
            font-weight: bold;
            margin-top: 15px;
            font-size: 16px;
            background-color: #f9f9f9;
            padding: 12px 15px;
            border-radius: 8px;
        }

        .order-total span {
            color: var(--primary-color);
            font-size: 18px;
            margin-left: 5px;
        }

        .order-steps {
            margin-top: 30px;
        }

        .order-steps h3 {
            font-size: 18px;
            margin-bottom: 20px;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .order-steps h3 i {
            color: var(--primary-color);
        }

        .steps-timeline {
            position: relative;
            margin: 30px 0;
            padding-left: 30px;
        }

        .steps-timeline:before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            height: 100%;
            width: 2px;
            background-color: #eee;
        }

        .step-item {
            position: relative;
            padding: 0 0 30px 30px;
        }

        .step-item:last-child {
            padding-bottom: 0;
        }

        .step-item:before {
            content: '';
            position: absolute;
            left: -30px;
            top: 0;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: #eee;
            z-index: 1;
        }

        .step-item.complete:before {
            background-color: var(--success-color);
        }

        .step-item.current:before {
            background-color: var(--primary-color);
        }

        .step-name {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .step-date {
            font-size: 13px;
            color: var(--text-light);
            margin-bottom: 5px;
        }

        .step-details {
            font-size: 14px;
            color: var(--text-color);
            margin-top: 5px;
            line-height: 1.5;
        }

        .step-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            margin-top: 5px;
        }

        .step-status.en_attente {
            background-color: rgba(255, 215, 0, 0.2);
            color: #B7950B;
        }

        .step-status.complete {
            background-color: rgba(76, 175, 80, 0.2);
            color: #27AE60;
        }

        .no-orders {
            text-align: center;
            padding: 40px 0;
        }

        .no-orders i {
            font-size: 60px;
            color: #e0e0e0;
            margin-bottom: 20px;
        }

        .no-orders p {
            font-size: 18px;
            color: var(--text-light);
        }

        footer {
            background-color: white;
            border-top: 1px solid #eee;
            padding: 50px 0 20px;
            margin-top: 60px;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 40px;
        }

        .footer-column h4 {
            font-size: 16px;
            margin-bottom: 20px;
            color: var(--primary-color);
            position: relative;
            padding-bottom: 10px;
        }

        .footer-column h4:after {
            content: '';
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
            margin-bottom: 12px;
        }

        .footer-column a {
            text-decoration: none;
            color: var(--text-light);
            font-size: 14px;
            transition: color var(--transition-speed);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .footer-column a:hover {
            color: var(--primary-color);
        }

        .copyright {
            max-width: 1200px;
            margin: 30px auto 0;
            padding: 20px 20px 0;
            border-top: 1px solid #eee;
            text-align: center;
            color: var(--text-light);
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                align-items: flex-start;
            }

            .logo {
                margin-bottom: 10px;
            }

            .search-container {
                width: 100%;
                max-width: none;
                margin: 10px 0;
            }

            .header-actions {
                margin-top: 10px;
                width: 100%;
                justify-content: space-between;
            }

            .order-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .order-status {
                margin: 10px 0;
            }

            .order-actions {
                width: 100%;
                justify-content: space-between;
            }

            .product-item {
                flex-direction: column;
            }

            .product-item img {
                margin-right: 0;
                margin-bottom: 10px;
                width: 100%;
                height: 150px;
            }

            .product-price {
                text-align: left;
                margin-top: 10px;
            }
        }
    </style>
<body>

<header>
    <div class="header-container">
        <div class="logo">
            <a href="index.php">BANDER-SHOP</a>
        </div>
        <div class="search-container">
            <form class="search-bar" action="index.php" method="get">
                <input type="text" placeholder="Rechercher des articles..." name="search">
                <button type="submit">🔍</button>
            </form>
        </div>
        <div class="header-actions">
            <a href="compte.php"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></a>
            <a href="messages.php">Messages</a>
            <a href="mes_articles.php">Mes Articles</a>
            <a href="logout.php">Déconnexion</a>
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
    <h1 class="page-title">Suivi de vos commandes</h1>

    <?php if (empty($commandes)): ?>
        <div class="orders-container">
            <p>Aucune commande trouvée.</p>
        </div>
    <?php else: ?>
        <div class="orders-container">
            <ul class="orders-list">
                <?php foreach ($commandes as $commande): ?>
                    <li class="order-item">
                        <div class="order-info">
                            <div class="order-number"><?= htmlspecialchars($commande['numero_commande']) ?></div>
                            <div class="order-details">
                                Date: <?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?> | 
                                <?= $commande['nombre_produits'] ?> article<?= $commande['nombre_produits'] > 1 ? 's' : '' ?>
                            </div>
                        </div>
                        <span class="order-status <?= htmlspecialchars($commande['statut']) ?>">
                            <?= htmlspecialchars($commande['statut']) ?>
                        </span>
                        <div class="order-actions">
                            <a href="suivi_commande.php?commande_id=<?= $commande['id'] ?>" class="order-link">Voir détails</a>
                            <?php if ($commande['statut'] === 'en_preparation'): ?>
                                <a href="annuler_commande.php?commande_id=<?= $commande['id'] ?>" class="cancel-button">Annuler</a>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($commande_details): ?>
        <div class="order-details-container">
            <div class="order-header">
                <h2 class="order-title">Commande #<?= htmlspecialchars($commande_details['numero_commande']) ?></h2>
                <div class="order-meta">
                    Passée le <?= date('d/m/Y H:i', strtotime($commande_details['date_commande'])) ?> | 
                    Statut: <span class="order-status <?= htmlspecialchars($commande_details['statut']) ?>">
                        <?= htmlspecialchars($commande_details['statut']) ?>
                    </span>
                </div>
            </div>
            <div class="products-list">
                <h3>Articles commandés</h3>
                <?php foreach ($produits_commande as $produit): ?>
                    <div class="product-item">
                        <img src="<?= htmlspecialchars($produit['image_url']) ?>" alt="<?= htmlspecialchars($produit['titre']) ?>">
                        <div class="product-info">
                            <div class="product-name"><?= htmlspecialchars($produit['titre']) ?></div>
                            <div class="product-details">
                                Catégorie: <?= htmlspecialchars($produit['categorie_nom'] ?? 'Non catégorisé') ?> | 
                                Quantité: <?= $produit['quantite'] ?>
                            </div>
                        </div>
                        <div class="product-price">
                            <?= number_format($produit['prix_unitaire'] * $produit['quantite'], 2, ',', ' ') ?> €
                        </div>
                    </div>
                <?php endforeach; ?>
                <div style="text-align: right; font-weight: bold; margin-top: 10px;">
                    Total: <?= number_format($commande_details['montant_total'], 2, ',', ' ') ?> €
                </div>
            </div>
            <div class="order-steps">
                <h3>Étapes de la commande</h3>
                <ul class="steps-list">
                    <?php foreach ($etapes_commande as $etape): ?>
                        <li class="step-item">
                            <div>
                                <div class="step-name"><?= htmlspecialchars($etape['etape_nom']) ?></div>
                                <div class="step-date"><?= date('d/m/Y H:i', strtotime($etape['date_etape'])) ?></div>
                            </div>
                            <span class="step-status <?= htmlspecialchars($etape['statut']) ?>">
                                <?= htmlspecialchars($etape['statut']) ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>
</main>

<footer>
    <div class="footer-container">
        <div class="footer-column">
            <h4>BANDER-SHOP</h4>
            <ul>
                <li><a href="#">À propos</a></li>
                <li><a href="#">Comment ça marche</a></li>
                <li><a href="#">Confiance et sécurité</a></li>
            </ul>
        </div>
        <div class="footer-column">
            <h4>Découvrir</h4>
            <ul>
                <li><a href="#">Applications mobiles</a></li>
                <li><a href="#">Tableau de bord</a></li>
            </ul>
        </div>
        <div class="footer-column">
            <h4>Aide</h4>
            <ul>
                <li><a href="#">Centre d'aide</a></li>
                <li><a href="#">Vendre</a></li>
                <li><a href="#">Acheter</a></li>
            </ul>
        </div>
    </div>
    <div class="copyright">
        <p>© 2025 BANDER-SHOP - Tous droits réservés.</p>
    </div>
</footer>

</body>
</html>