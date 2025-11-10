<?php
session_start();

// Vérifiez si l'utilisateur est connecté
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

// Filtrer les catégories pour ne pas afficher Homme et Femme
$categories_filtrees = array_filter($categories, function($category) {
    return $category['nom'] !== 'Homme' && $category['nom'] !== 'Femme';
});

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Récupérer le nombre de produits de l'utilisateur
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM products WHERE vendeur_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$produits_count = $stmt->fetch()['total'];

// Récupérer le nombre de produits vendus
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM products WHERE vendeur_id = ? AND status = 'vendu'");
$stmt->execute([$_SESSION['user_id']]);
$produits_vendus_count = $stmt->fetch()['total'];

// Récupérer le nombre de commandes passées
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE acheteur_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$commandes_count = $stmt->fetch()['total'];

// Récupérer le nombre de produits achetés (pour une vraie application, cela viendrait de la table des commandes)
// Ici, nous utilisons une simulation
$produits_achetes_count = 0; // À remplacer par une vraie requête

// Vérifiez si l'utilisateur existe
if (!$user) {
    echo "Utilisateur non trouvé.";
    exit();
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Compte - BANDER-SHOP</title>
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

        /* Account layout */
        .account-container {
            display: flex;
            gap: 40px;
            flex-wrap: wrap;
        }

        @media (max-width: 992px) {
            .account-container {
                flex-direction: column;
            }
        }

        /* Left column - Account menu */
        .account-menu {
            flex: 1;
            min-width: 280px;
            max-width: 320px;
        }

        @media (max-width: 992px) {
            .account-menu {
                max-width: 100%;
            }
        }

        .profile-card {
            background-color: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
            transition: transform var(--transition-speed);
        }

        .profile-card:hover {
            transform: translateY(-5px);
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background-color: var(--light-gray);
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            font-weight: 700;
            margin: 0 auto 20px;
            box-shadow: 0 5px 15px rgba(255, 140, 0, 0.15);
            border: 4px solid white;
        }

        .profile-name {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--secondary-color);
        }

        .profile-email {
            font-size: 14px;
            color: #777;
            margin-bottom: 20px;
            overflow: hidden;
            text-overflow: ellipsis;
            word-break: break-all;
        }

        .menu-links {
            background-color: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .menu-links a {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            text-decoration: none;
            color: var(--text-color);
            border-bottom: 1px solid var(--border-color);
            transition: all var(--transition-speed);
            font-weight: 500;
        }

        .menu-links a i {
            margin-right: 12px;
            font-size: 18px;
            width: 24px;
            text-align: center;
            color: var(--primary-color);
        }

        .menu-links a:last-child {
            border-bottom: none;
        }

        .menu-links a:hover, .menu-links a.active {
            background-color: var(--light-gray);
            color: var(--primary-color);
            padding-left: 25px;
        }

        .menu-links a.danger {
            color: #e74c3c;
        }

        .menu-links a.danger i {
            color: #e74c3c;
        }

        .menu-links a.danger:hover {
            background-color: #ffeeee;
        }

        /* Right column - Account content */
        .account-content {
            flex: 3;
            min-width: 0;
        }

        .account-card {
            background-color: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 30px;
            margin-bottom: 30px;
            transition: transform var(--transition-speed);
        }

        .account-card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .card-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--secondary-color);
            display: flex;
            align-items: center;
        }

        .card-title i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        .card-action {
            font-size: 14px;
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            font-weight: 600;
            transition: all var(--transition-speed);
        }

        .card-action i {
            margin-left: 5px;
        }

        .card-action:hover {
            color: #E07B00;
            transform: translateX(3px);
        }

        /* Personal information */
        .info-list {
            list-style: none;
        }

        .info-item {
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            flex: 1;
            min-width: 150px;
            font-weight: 600;
            color: #777;
            display: flex;
            align-items: center;
        }

        .info-label i {
            margin-right: 10px;
            color: var(--primary-color);
            width: 20px;
            text-align: center;
        }

        .info-value {
            flex: 2;
            word-break: break-word;
            font-weight: 500;
            color: var(--secondary-color);
        }

        /* Activity summary */
        .activity-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 25px;
            margin-top: 15px;
        }

        .activity-card {
            background-color: var(--light-gray);
            border-radius: var(--radius);
            padding: 25px 20px;
            text-align: center;
            transition: all var(--transition-speed);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: inherit;
        }

        .activity-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
            border-color: var(--primary-color);
        }

        .activity-icon {
            font-size: 32px;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .activity-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 10px;
            line-height: 1;
        }

        .activity-label {
            font-size: 14px;
            color: #777;
            font-weight: 500;
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
            .account-container {
                gap: 30px;
            }
        }

        @media (max-width: 992px) {
            .account-menu {
                max-width: 100%;
            }
            
            .profile-card {
                display: flex;
                align-items: center;
                text-align: left;
                padding: 20px;
            }
            
            .profile-avatar {
                margin: 0 20px 0 0;
                width: 80px;
                height: 80px;
                font-size: 28px;
            }
            
            .profile-info {
                flex: 1;
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
            
            .activity-grid {
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                gap: 15px;
            }
            
            .activity-number {
                font-size: 28px;
            }
            
            .activity-icon {
                font-size: 28px;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .card-action {
                align-self: flex-end;
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
            
            .info-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .info-label {
                margin-bottom: 5px;
            }
            
            .info-value {
                width: 100%;
            }
            
            .activity-grid {
                grid-template-columns: repeat(2, 1fr);
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
            <a href="compte.php"><i class="fas fa-user-circle"></i> <strong><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></strong></a>
            <a href="messages.php"><i class="fas fa-envelope"></i> Messages</a>
            <a href="mes_articles.php"><i class="fas fa-box"></i> Mes Articles</a>
            <a href="suivi_commande.php"><i class="fas fa-truck"></i> Mes Commandes</a>
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
    <h1 class="page-title">Mon Compte</h1>

    <div class="account-container">
        <!-- Left column - Account menu -->
        <div class="account-menu">
            <div class="profile-card">
                <div class="profile-avatar">
                    <?= strtoupper(substr($user['prenom'], 0, 1) . (isset($user['nom']) ? substr($user['nom'], 0, 1) : '')) ?>
                </div>
                <div class="profile-info">
                    <div class="profile-name"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></div>
                    <div class="profile-email"><?= htmlspecialchars($user['email']) ?></div>
                </div>
            </div>

            <div class="menu-links">
                <a href="compte.php" class="active"><i class="fas fa-user"></i> Informations personnelles</a>
                <a href="mes_articles.php"><i class="fas fa-box"></i> Mes articles à vendre</a>
                <a href="suivi_commande.php"><i class="fas fa-truck"></i> Suivi de commandes</a>
                <a href="messages.php"><i class="fas fa-envelope"></i> Messages</a>
                <a href="panier.php"><i class="fas fa-shopping-cart"></i> Mon panier</a>
                <a href="favoris.php"><i class="fas fa-heart"></i> Mes favoris</a>
                <a href="parametres.php"><i class="fas fa-cog"></i> Paramètres</a>
                <a href="logout.php" class="danger"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
            </div>
        </div>

        <!-- Right column - Account content -->
        <div class="account-content">
            <!-- Personal information -->
            <div class="account-card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-id-card"></i> Informations personnelles</h2>
                    <a href="modifier_profil.php" class="card-action">Modifier <i class="fas fa-chevron-right"></i></a>
                </div>

                <ul class="info-list">
                    <li class="info-item">
                        <span class="info-label"><i class="fas fa-user"></i> Prénom</span>
                        <span class="info-value"><?= htmlspecialchars($user['prenom']) ?></span>
                    </li>
                    <li class="info-item">
                        <span class="info-label"><i class="fas fa-user"></i> Nom</span>
                        <span class="info-value"><?= htmlspecialchars($user['nom']) ?></span>
                    </li>
                    <li class="info-item">
                        <span class="info-label"><i class="fas fa-envelope"></i> Email</span>
                        <span class="info-value"><?= htmlspecialchars($user['email']) ?></span>
                    </li>
                    <?php if (isset($user['age']) && $user['age']): ?>
                    <li class="info-item">
                        <span class="info-label"><i class="fas fa-birthday-cake"></i> Âge</span>
                        <span class="info-value"><?= htmlspecialchars($user['age']) ?> ans</span>
                    </li>
                    <?php endif; ?>
                    <?php if (isset($user['adresse']) && $user['adresse']): ?>
                    <li class="info-item">
                        <span class="info-label"><i class="fas fa-map-marker-alt"></i> Adresse</span>
                        <span class="info-value"><?= htmlspecialchars($user['adresse']) ?></span>
                    </li>
                    <?php endif; ?>
                    <?php if (isset($user['telephone']) && $user['telephone']): ?>
                    <li class="info-item">
                        <span class="info-label"><i class="fas fa-phone"></i> Téléphone</span>
                        <span class="info-value"><?= htmlspecialchars($user['telephone']) ?></span>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Activity summary -->
            <div class="account-card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-chart-line"></i> Mon activité</h2>
                </div>

                <div class="activity-grid">
                    <div class="activity-card">
                        <div class="activity-icon"><i class="fas fa-box"></i></div>
                        <div class="activity-number"><?= $produits_count ?></div>
                        <div class="activity-label">Articles à vendre</div>
                    </div>
                    <div class="activity-card">
                        <div class="activity-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="activity-number"><?= $produits_vendus_count ?></div>
                        <div class="activity-label">Articles vendus</div>
                    </div>
                    <div class="activity-card">
                        <div class="activity-icon"><i class="fas fa-shopping-bag"></i></div>
                        <div class="activity-number"><?= $produits_achetes_count ?></div>
                        <div class="activity-label">Articles achetés</div>
                    </div>
                    <div class="activity-card">
                        <div class="activity-icon"><i class="fas fa-receipt"></i></div>
                        <div class="activity-number"><?= $commandes_count ?></div>
                        <div class="activity-label">Commandes passées</div>
                    </div>
                    <a href="suivi_commande.php" class="activity-card">
                        <div class="activity-icon"><i class="fas fa-truck"></i></div>
                        <div class="activity-label">Suivi de commandes</div>
                    </a>
                </div>
            </div>
        </div>
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