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

// Récupérer le produit spécifique
if (isset($_GET['id'])) {
    $id_produit = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id_produit]);
    $produit = $stmt->fetch();

    if (!$produit) {
        header('Location: index.php');
        exit;
    }
    
    // Vérifier si le produit est déjà vendu
    if (isset($produit['status']) && $produit['status'] === 'vendu') {
        $_SESSION['message'] = "Ce produit n'est plus disponible à l'achat.";
        header('Location: index.php');
        exit;
    }
    
    // Vérifier si l'utilisateur est le vendeur du produit
    $est_vendeur = false;
    if (isset($_SESSION['user_id']) && isset($produit['vendeur_id']) && $_SESSION['user_id'] == $produit['vendeur_id']) {
        $est_vendeur = true;
    }
} else {
    header('Location: index.php');
    exit;
}

// Traiter l'achat du produit - Rediriger vers la page d'achat direct
if (isset($_POST['acheter'])) {
    // Vérifie si l'utilisateur est connecté
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['message'] = "Vous devez être connecté pour acheter un produit.";
        header('Location: connexion.php');
        exit;
    }
    
    // Vérifier si l'utilisateur tente d'acheter son propre produit
    if ($est_vendeur) {
        $_SESSION['erreur'] = "Vous ne pouvez pas acheter votre propre produit.";
        header('Location: produit.php?id=' . $id_produit);
        exit;
    }
    
    // Enregistrer l'ID du produit dans la session pour l'achat direct
    $_SESSION['achat_direct'] = [
        'produit_id' => $id_produit
    ];
    
    // Rediriger vers la page d'achat direct
    header('Location: checkoutdirect.php');
    exit;
}

// Traiter l'ajout au panier
if (isset($_POST['ajouter_panier'])) {
    // Vérifier si l'utilisateur tente d'ajouter son propre produit au panier
    if ($est_vendeur) {
        $_SESSION['erreur'] = "Vous ne pouvez pas ajouter votre propre produit au panier.";
        header('Location: produit.php?id=' . $id_produit);
        exit;
    }
    
    $id_produit = isset($_POST['id_produit']) ? intval($_POST['id_produit']) : 0;
    
    // Vérifie si le produit est déjà dans le panier
    if (isset($_SESSION['panier'][$id_produit])) {
        $_SESSION['panier'][$id_produit]++; // Incrémente la quantité
    } else {
        $_SESSION['panier'][$id_produit] = 1; // Ajoute le produit avec une quantité de 1
    }

    $_SESSION['message'] = "Produit ajouté au panier.";
    
    // Rediriger pour éviter de renvoyer le formulaire lors d'un rafraîchissement
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($produit['titre']) ?> - BANDER-SHOP</title>
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

        /* Message de notification */
        .notification {
            background-color: var(--light-gray);
            color: var(--secondary-color);
            padding: 15px 20px;
            margin: 20px auto;
            max-width: 1400px;
            border-radius: var(--radius);
            text-align: center;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow);
        }

        .notification i {
            margin-right: 10px;
            font-size: 18px;
        }

        .notification.success {
            background-color: #E3F9E5;
            color: #1B873F;
        }

        .notification.error {
            background-color: #FFEEEE;
            color: var(--error-color);
        }

        /* Product page */
        main {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 30px;
        }

        .product-container {
            background-color: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        @media (min-width: 992px) {
            .product-container {
                flex-direction: row;
                min-height: 600px;
            }
        }

        .product-image {
            flex: 1;
            padding: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--light-gray);
            position: relative;
        }

        .product-image img {
            max-width: 100%;
            max-height: 500px;
            object-fit: contain;
            border-radius: var(--radius);
            transition: transform var(--transition-speed);
            box-shadow: var(--shadow);
        }

        .product-image:hover img {
            transform: scale(1.03);
        }

        .product-details {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
        }

        .product-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--secondary-color);
            line-height: 1.3;
        }

        .product-price {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
        }

        .product-price::before {
            content: "€";
            margin-right: 5px;
            font-size: 22px;
        }

        .product-description {
            margin-bottom: 30px;
            line-height: 1.8;
            color: var(--text-color);
            font-size: 16px;
            white-space: pre-line;
        }

        /* Product actions */
        .product-actions {
            margin-top: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .action-button {
            padding: 16px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-speed);
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .action-button i {
            margin-right: 8px;
            font-size: 18px;
        }

        .buy-button {
            background-color: var(--primary-color);
            color: white;
            box-shadow: 0 4px 10px rgba(255, 140, 0, 0.2);
        }

        .buy-button:hover {
            background-color: #E07B00;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 140, 0, 0.25);
        }

        .add-to-cart-button {
            background-color: var(--light-gray);
            color: var(--secondary-color);
            border: 1px solid var(--border-color);
        }

        .add-to-cart-button:hover {
            background-color: var(--border-color);
            color: var(--primary-color);
        }

        .message-button {
            background-color: white;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }

        .message-button:hover {
            background-color: var(--light-gray);
            transform: translateY(-2px);
        }

        .disabled-button {
            background-color: #ccc;
            color: #666;
            cursor: not-allowed;
        }

        .disabled-button:hover {
            background-color: #ccc;
            transform: none;
            box-shadow: none;
        }

        /* Additional product info */
        .product-meta {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            font-size: 14px;
            color: #777;
        }

        .product-meta p {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }

        .product-meta p i {
            width: 20px;
            margin-right: 10px;
            color: var(--primary-color);
        }

        .seller-badge {
            display: inline-block;
            background-color: var(--light-gray);
            padding: 5px 12px;
            border-radius: 20px;
            color: var(--primary-color);
            font-weight: 600;
            margin-left: 10px;
            font-size: 12px;
            text-transform: uppercase;
        }

        /* Status badge */
        .status-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            z-index: 1;
            box-shadow: var(--shadow);
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
        @media (max-width: 992px) {
            .product-image {
                padding: 20px;
            }
            
            .product-details {
                padding: 30px;
            }
            
            .product-title {
                font-size: 28px;
            }
            
            .product-price {
                font-size: 24px;
            }
        }

        @media (max-width: 768px) {
            .header-container {
                flex-wrap: wrap;
                gap: 20px;
            }
            
            .header-actions {
                gap: 15px;
            }
            
            .product-title {
                font-size: 24px;
            }
            
            .product-price {
                font-size: 22px;
            }
            
            .action-button {
                padding: 14px;
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
            
            .product-image {
                padding: 15px;
            }
            
            .product-details {
                padding: 20px;
            }
            
            .product-title {
                font-size: 22px;
            }
            
            .product-price {
                font-size: 20px;
            }
            
            .action-button {
                padding: 12px;
                font-size: 14px;
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

        <div class="header-actions">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="compte.php"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['user_nom']); ?></a>
                <a href="messages.php"><i class="fas fa-envelope"></i> Messages</a>
                <a href="panier.php"><i class="fas fa-shopping-cart"></i> Panier (<?= isset($_SESSION['panier']) ? array_sum($_SESSION['panier']) : 0 ?>)</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
            <?php else: ?>
                <a href="connexion.php"><i class="fas fa-sign-in-alt"></i> Se connecter</a>
                <a href="inscription.php"><i class="fas fa-user-plus"></i> S'inscrire</a>
                <a href="panier.php"><i class="fas fa-shopping-cart"></i> Panier (<?= isset($_SESSION['panier']) ? array_sum($_SESSION['panier']) : 0 ?>)</a>
            <?php endif; ?>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="mes_articles.php" class="sell-button"><i class="fas fa-tag"></i> Vends tes articles</a>
            <?php else: ?>
                <a href="connexion.php" class="sell-button"><i class="fas fa-tag"></i> Vends tes articles</a>
            <?php endif; ?>
        </div>
    </div>

    <nav class="category-nav">
        <ul>
            <li><a href="index.php" class="<?= !isset($_GET['categorie_id']) ? 'active' : '' ?>">Tous les produits</a></li>
            <?php
            foreach ($categories as $category):
                $isActive = (isset($_GET['categorie_id']) && $_GET['categorie_id'] == $category['id']);
                // Ne pas afficher les catégories Homme et Femme
                if ($category['nom'] !== 'Homme' && $category['nom'] !== 'Femme'):
            ?>
                <li>
                    <a href="index.php?categorie_id=<?= $category['id'] ?>" class="<?= $isActive ? 'active' : '' ?>">
                        <?= htmlspecialchars($category['nom']) ?>
                    </a>
                </li>
            <?php 
                endif;
            endforeach; 
            ?>
        </ul>
    </nav>
</header>

<?php if (isset($_SESSION['erreur'])): ?>
    <div class="notification error">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['erreur']) ?>
        <?php unset($_SESSION['erreur']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['message'])): ?>
    <div class="notification success">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['message']) ?>
        <?php unset($_SESSION['message']); ?>
    </div>
<?php endif; ?>

<main>
    <div class="product-container">
        <div class="product-image">
            <?php if (isset($produit['status'])): ?>
                <div class="status-badge status-<?= strtolower($produit['status']) ?>">
                    <?= htmlspecialchars($produit['status']) ?>
                </div>
            <?php endif; ?>
            <img src="<?= htmlspecialchars($produit['image_url']) ?>" alt="<?= htmlspecialchars($produit['titre']) ?>">
        </div>
        <div class="product-details">
            <h1 class="product-title"><?= htmlspecialchars($produit['titre']) ?></h1>
            <p class="product-price"><?= number_format($produit['prix'], 2, ',', ' ') ?></p>
            <div class="product-description">
                <?= nl2br(htmlspecialchars($produit['description'])) ?>
            </div>

            <div class="product-meta">
                <p><i class="fas fa-tag"></i> <strong>Catégorie:</strong>
                    <?php
                    foreach ($categories as $category) {
                        if ($category['id'] == $produit['categorie_id']) {
                            echo htmlspecialchars($category['nom']);
                            break;
                        }
                    }
                    ?>
                </p>
                <p><i class="fas fa-hashtag"></i> <strong>Référence:</strong> #<?= $produit['id'] ?></p>
                <?php if (isset($produit['status'])): ?>
                <p><i class="fas fa-info-circle"></i> <strong>Statut:</strong> <?= htmlspecialchars($produit['status']) ?></p>
                <?php endif; ?>
                <?php if ($est_vendeur): ?>
                <p><i class="fas fa-user"></i> <strong>Vendeur:</strong> Vous <span class="seller-badge">Votre article</span></p>
                <?php endif; ?>
            </div>

            <div class="product-actions">
                <?php if (!$est_vendeur): ?>
                    <form method="post" action="produit.php?id=<?= $produit['id'] ?>">
                        <button type="submit" name="acheter" class="action-button buy-button">
                            <i class="fas fa-shopping-bag"></i> Acheter maintenant
                        </button>
                    </form>

                    <form method="post" action="produit.php?id=<?= $produit['id'] ?>">
                        <input type="hidden" name="id_produit" value="<?= $produit['id'] ?>">
                        <button type="submit" name="ajouter_panier" class="action-button add-to-cart-button">
                            <i class="fas fa-cart-plus"></i> Ajouter au panier
                        </button>
                    </form>

                    <a href="messages.php?receiver_id=<?= isset($produit['vendeur_id']) ? $produit['vendeur_id'] : 1 ?>" class="action-button message-button">
                        <i class="fas fa-envelope"></i> Envoyer un message
                    </a>
                <?php else: ?>
                    <button class="action-button disabled-button" disabled>
                        <i class="fas fa-exclamation-circle"></i> Vous ne pouvez pas acheter votre propre produit
                    </button>
                    <a href="mes_articles.php" class="action-button message-button">
                        <i class="fas fa-box"></i> Gérer mes articles
                    </a>
                <?php endif; ?>
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