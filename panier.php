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

// Vérifier si le panier existe
if (!isset($_SESSION['panier']) || empty($_SESSION['panier'])) {
    $panier_vide = true;
    $panier_total = 0;
} else {
    // Vérifier si les produits du panier sont toujours disponibles (non vendus)
    $panier_ids = array_keys($_SESSION['panier']);
    $produits_non_disponibles = [];

    if (!empty($panier_ids)) {
        $placeholders = str_repeat('?,', count($panier_ids) - 1) . '?';

        // Récupérer les produits qui ont été vendus
        $stmt = $pdo->prepare("SELECT id FROM products WHERE id IN ($placeholders) AND (status = 'vendu' OR status IS NULL)");
        $stmt->execute($panier_ids);
        $produits_non_disponibles = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Supprimer les produits non disponibles du panier
        if (!empty($produits_non_disponibles)) {
            foreach ($produits_non_disponibles as $id) {
                unset($_SESSION['panier'][$id]);
            }

            // Afficher un message à l'utilisateur si des produits ont été retirés
            $_SESSION['message'] = "Certains articles de votre panier n'étaient plus disponibles et ont été retirés.";
        }
    }

    $panier_vide = empty($_SESSION['panier']);
    $panier_total = 0;
    $produits_panier = [];

    if (!$panier_vide) {
        foreach ($_SESSION['panier'] as $id_produit => $quantite) {
            // Récupérer les détails des produits du panier (seulement les disponibles)
            $stmt = $pdo->prepare("SELECT p.*, c.nom AS categorie_nom FROM products p
                                  LEFT JOIN categories c ON p.categorie_id = c.id
                                  WHERE p.id = ? AND (p.status = 'disponible' OR p.status IS NULL)");
            $stmt->execute([$id_produit]);
            $produit = $stmt->fetch();

            if ($produit) {
                $produit['quantite'] = $quantite;
                $produit['sous_total'] = $produit['prix'] * $quantite;
                $panier_total += $produit['sous_total'];
                $produits_panier[] = $produit;
            } else {
                // Si le produit n'est pas trouvé ou n'est plus disponible, le retirer du panier
                unset($_SESSION['panier'][$id_produit]);
            }
        }

        // Si tous les produits ont été retirés après vérification
        if (empty($produits_panier)) {
            $panier_vide = true;
        }
    }
}

// Traitement de la suppression, modification ou ajout de quantité
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'supprimer') {
        $id_produit = $_POST['id_produit'];
        unset($_SESSION['panier'][$id_produit]);
        $_SESSION['message'] = "Article retiré du panier.";
        header('Location: panier.php');
        exit();
    } elseif ($_POST['action'] == 'modifier') {
        $id_produit = $_POST['id_produit'];
        $quantite = intval($_POST['quantite']);

        if ($quantite > 0) {
            $_SESSION['panier'][$id_produit] = $quantite;
        } else {
            unset($_SESSION['panier'][$id_produit]);
        }

        header('Location: panier.php');
        exit();
    } elseif ($_POST['action'] == 'ajouter') {
        $id_produit = $_POST['id_produit'];

        // Vérifier si le produit est toujours disponible
        $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND (status = 'disponible' OR status IS NULL)");
        $stmt->execute([$id_produit]);
        $produit = $stmt->fetch();

        if ($produit) {
            // Si le produit est déjà dans le panier, on augmente la quantité
            if (isset($_SESSION['panier'][$id_produit])) {
                $_SESSION['panier'][$id_produit]++;
            } else {
                $_SESSION['panier'][$id_produit] = 1;
            }
            $_SESSION['message'] = "Article ajouté au panier.";
        } else {
            $_SESSION['erreur'] = "Désolé, ce produit n'est plus disponible.";
        }

        header('Location: panier.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Panier - BANDER-SHOP</title>
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

        /* Cart styles */
        .cart-container {
            background-color: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 40px;
            margin-bottom: 40px;
        }

        .empty-cart {
            text-align: center;
            padding: 60px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .empty-cart-icon {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 25px;
        }

        .empty-cart-message {
            font-size: 18px;
            color: #777;
            margin-bottom: 30px;
            max-width: 500px;
        }

        .continue-shopping {
            display: inline-flex;
            align-items: center;
            background-color: var(--primary-color);
            color: white;
            padding: 14px 28px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all var(--transition-speed);
            box-shadow: 0 4px 10px rgba(255, 140, 0, 0.2);
            font-size: 16px;
        }

        .continue-shopping i {
            margin-right: 8px;
        }

        .continue-shopping:hover {
            background-color: #E07B00;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 140, 0, 0.25);
        }

        .cart-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .cart-items th {
            padding: 15px;
            text-align: left;
            border-bottom: 2px solid var(--border-color);
            color: var(--secondary-color);
            font-weight: 600;
            font-size: 15px;
        }

        .cart-items td {
            padding: 20px 15px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .cart-item-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: var(--shadow);
            transition: transform var(--transition-speed);
        }

        .cart-item-image:hover {
            transform: scale(1.05);
        }

        .cart-item-details {
            padding-left: 20px;
        }

        .cart-item-title {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--secondary-color);
            text-decoration: none;
            font-size: 16px;
            display: block;
            transition: color var(--transition-speed);
        }

        .cart-item-title:hover {
            color: var(--primary-color);
        }

        .cart-item-category {
            font-size: 13px;
            color: #777;
            display: flex;
            align-items: center;
        }

        .cart-item-category i {
            margin-right: 5px;
            color: var(--primary-color);
            font-size: 12px;
        }

        .cart-item-price {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 18px;
        }

        .cart-quantity {
            display: flex;
            align-items: center;
        }

        .cart-quantity-input {
            width: 60px;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            text-align: center;
            margin-right: 10px;
            font-size: 15px;
            transition: all var(--transition-speed);
        }

        .cart-quantity-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 140, 0, 0.1);
        }

        .cart-action-button {
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all var(--transition-speed);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cart-action-button i {
            margin-right: 5px;
        }

        .update-button {
            background-color: var(--light-gray);
            color: var(--secondary-color);
            border: 1px solid var(--border-color);
        }

        .update-button:hover {
            background-color: var(--border-color);
            color: var(--primary-color);
        }

        .remove-button {
            background-color: #FFF5F5;
            color: var(--error-color);
            border: 1px solid #FFDDDD;
        }

        .remove-button:hover {
            background-color: #FFE8E8;
        }

        .cart-summary {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 20px;
            padding-top: 30px;
            border-top: 2px solid var(--border-color);
        }

        .cart-total {
            font-size: 22px;
            font-weight: 700;
            color: var(--secondary-color);
            display: flex;
            align-items: center;
        }

        .cart-total-amount {
            color: var(--primary-color);
            margin-left: 10px;
        }

        .checkout-button {
            display: inline-flex;
            align-items: center;
            background-color: var(--primary-color);
            color: white;
            padding: 16px 32px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all var(--transition-speed);
            box-shadow: 0 4px 10px rgba(255, 140, 0, 0.2);
        }

        .checkout-button i {
            margin-right: 8px;
        }

        .checkout-button:hover {
            background-color: #E07B00;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 140, 0, 0.25);
        }

        /* Responsive cart */
        @media (max-width: 992px) {
            .cart-items {
                display: block;
                overflow-x: auto;
            }

            .cart-items th, .cart-items td {
                min-width: 120px;
            }

            .cart-items th:first-child, .cart-items td:first-child {
                min-width: 240px;
            }
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
            
            .cart-container {
                padding: 30px 20px;
            }
            
            .cart-summary {
                align-items: center;
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
            
            .cart-item-image {
                width: 80px;
                height: 80px;
            }
            
            .cart-item-details {
                padding-left: 10px;
            }
            
            .cart-item-title {
                font-size: 14px;
            }
            
            .cart-item-price {
                font-size: 16px;
            }
            
            .cart-action-button {
                padding: 8px 12px;
                font-size: 12px;
            }
            
            .checkout-button {
                padding: 14px 24px;
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

        <div class="search-container">
            <form class="search-bar" action="index.php" method="get">
                <input type="text" placeholder="Rechercher des articles..." name="search">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>

        <div class="header-actions">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="compte.php"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['user_nom']); ?></a>
                <a href="messages.php"><i class="fas fa-envelope"></i> Messages</a>
                <a href="mes_articles.php"><i class="fas fa-box"></i> Mes Articles</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
            <?php else: ?>
                <a href="connexion.php"><i class="fas fa-sign-in-alt"></i> Se connecter</a>
                <a href="inscription.php"><i class="fas fa-user-plus"></i> S'inscrire</a>
            <?php endif; ?>
            <a href="<?= isset($_SESSION['user_id']) ? 'mes_articles.php' : 'connexion.php' ?>" class="sell-button"><i class="fas fa-tag"></i> Vends tes articles</a>
        </div>
    </div>

    <nav class="category-nav">
        <ul>
            <?php foreach ($categories as $category): ?>
                <li>
                    <a href="index.php?categorie_id=<?= $category['id'] ?>">
                        <?= htmlspecialchars($category['nom']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
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
    <h1 class="page-title">Mon Panier</h1>

    <div class="cart-container">
        <?php if ($panier_vide): ?>
            <div class="empty-cart">
                <div class="empty-cart-icon"><i class="fas fa-shopping-cart"></i></div>
                <p class="empty-cart-message">Votre panier est vide. Découvrez nos produits et commencez à faire vos achats!</p>
                <a href="index.php" class="continue-shopping"><i class="fas fa-arrow-left"></i> Continuer vos achats</a>
            </div>
        <?php else: ?>
            <table class="cart-items">
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Prix unitaire</th>
                        <th>Quantité</th>
                        <th>Sous-total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produits_panier as $produit): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center;">
                                    <a href="produit.php?id=<?= $produit['id'] ?>">
                                        <img src="<?= htmlspecialchars($produit['image_url']) ?>" alt="<?= htmlspecialchars($produit['titre']) ?>" class="cart-item-image">
                                    </a>
                                    <div class="cart-item-details">
                                        <a href="produit.php?id=<?= $produit['id'] ?>" class="cart-item-title"><?= htmlspecialchars($produit['titre']) ?></a>
                                        <div class="cart-item-category"><i class="fas fa-tag"></i> <?= htmlspecialchars($produit['categorie_nom'] ?? 'Non catégorisé') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="cart-item-price"><?= number_format($produit['prix'], 2, ',', ' ') ?> €</td>
                            <td class="cart-item-quantity">
                                <form method="post" action="panier.php" style="display: flex; align-items: center;">
                                    <input type="hidden" name="id_produit" value="<?= $produit['id'] ?>">
                                    <input type="number" name="quantite" value="<?= $produit['quantite'] ?>" min="1" class="cart-quantity-input">
                                    <button type="submit" name="action" value="modifier" class="cart-action-button update-button">
                                        <i class="fas fa-sync-alt"></i> Mettre à jour
                                    </button>
                                </form>
                            </td>
                            <td class="cart-item-price"><?= number_format($produit['sous_total'], 2, ',', ' ') ?> €</td>
                            <td>
                                <form method="post" action="panier.php">
                                    <input type="hidden" name="id_produit" value="<?= $produit['id'] ?>">
                                    <button type="submit" name="action" value="supprimer" class="cart-action-button remove-button">
                                        <i class="fas fa-trash-alt"></i> Supprimer
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="cart-summary">
                <div class="cart-total">
                    Total: <span class="cart-total-amount"><?= number_format($panier_total, 2, ',', ' ') ?> €</span>
                </div>
                <a href="checkout.php" class="checkout-button">
                    <i class="fas fa-credit-card"></i> Passer à la caisse
                </a>
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