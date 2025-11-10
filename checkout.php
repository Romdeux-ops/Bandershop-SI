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

// Vérifier si le panier existe et n'est pas vide
if (!isset($_SESSION['panier']) || empty($_SESSION['panier'])) {
    $_SESSION['erreur'] = "Votre panier est vide.";
    header("Location: panier.php");
    exit();
}

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Calculer le total du panier et récupérer les produits
$panier_total = 0;
$produits_panier = [];
foreach ($_SESSION['panier'] as $id_produit => $quantite) {
    $stmt = $pdo->prepare("SELECT p.*, c.nom AS categorie_nom FROM products p
                          LEFT JOIN categories c ON p.categorie_id = c.id
                          WHERE p.id = ? AND (p.status = 'disponible' OR p.status IS NULL)");
    $stmt->execute([$id_produit]);
    $produit = $stmt->fetch();

    if ($produit) {
        $produit['quantite'] = $quantite;
        $produit['sous_total'] = $produit['prix'] * $quantite;
        $panier_total += $produit['sous_total'];
        $produits_panier[$id_produit] = $produit;
    } else {
        unset($_SESSION['panier'][$id_produit]);
    }
}

if (empty($produits_panier)) {
    $_SESSION['erreur'] = "Aucun produit disponible dans votre panier.";
    header("Location: panier.php");
    exit();
}

// Traitement du formulaire de commande
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $adresse_livraison = htmlspecialchars($_POST['adresse_livraison']);
    $methode_paiement = htmlspecialchars($_POST['methode_paiement']);
    $numero_commande = 'CMD' . time() . rand(1000, 9999);

    // Insérer la commande dans la table orders
    $stmt = $pdo->prepare("
        INSERT INTO orders (numero_commande, acheteur_id, adresse_livraison, methode_paiement, montant_total, statut)
        VALUES (?, ?, ?, ?, ?, 'en_preparation')
    ");
    $stmt->execute([$numero_commande, $_SESSION['user_id'], $adresse_livraison, $methode_paiement, $panier_total]);
    $commande_id = $pdo->lastInsertId();

    // Insérer les produits dans order_products
    $stmt = $pdo->prepare("
        INSERT INTO order_products (order_id, product_id, quantite, prix_unitaire)
        VALUES (?, ?, ?, ?)
    ");
    foreach ($produits_panier as $id_produit => $produit) {
        $stmt->execute([$commande_id, $id_produit, $produit['quantite'], $produit['prix']]);
        
        // Mettre à jour le statut du produit à "vendu"
        $stmt_update = $pdo->prepare("UPDATE products SET status = 'vendu' WHERE id = ?");
        $stmt_update->execute([$id_produit]);
    }

    // Insérer les étapes de suivi
    $etapes = ['Commande confirmée', 'En préparation', 'Expédiée', 'En livraison', 'Livrée'];
    $stmt = $pdo->prepare("
        INSERT INTO order_steps (order_id, etape_nom, date_etape, statut)
        VALUES (?, ?, NOW(), ?)
    ");
    foreach ($etapes as $index => $etape) {
        $statut = ($index <= 1) ? 'complete' : 'en_attente';
        $stmt->execute([$commande_id, $etape, $statut]);
    }

    // Vider le panier après la commande réussie
    unset($_SESSION['panier']);

    // Message de confirmation
    $_SESSION['message'] = "Votre commande #$numero_commande a été passée avec succès !";
    header("Location: suivi_commande.php?commande_id=$commande_id");
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finaliser votre commande - BANDER-SHOP</title>
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
            --success-color: #2ecc71;
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

        /* Checkout layout */
        .checkout-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }

        @media (max-width: 992px) {
            .checkout-container {
                grid-template-columns: 1fr;
            }
        }

        /* Checkout form */
        .checkout-form {
            background-color: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 40px;
            transition: transform var(--transition-speed);
        }

        .checkout-form:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        }

        .checkout-section {
            margin-bottom: 30px;
        }

        .checkout-section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
        }

        .checkout-section-title i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
            color: var(--secondary-color);
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 15px;
            transition: all var(--transition-speed);
            background-color: var(--light-gray);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 140, 0, 0.1);
        }

        .payment-methods {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .payment-method {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            cursor: pointer;
            transition: all var(--transition-speed);
        }

        .payment-method:hover {
            border-color: var(--primary-color);
            background-color: var(--light-gray);
        }

        .payment-method.selected {
            border-color: var(--primary-color);
            background-color: rgba(255, 140, 0, 0.05);
        }

        .payment-method input[type="radio"] {
            margin-right: 15px;
        }

        .payment-method-icon {
            margin-right: 15px;
            font-size: 24px;
            color: var(--primary-color);
        }

        .payment-method-details {
            flex: 1;
        }

        .payment-method-title {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }

        .payment-method-description {
            font-size: 13px;
            color: #777;
        }

        /* Order summary */
        .order-summary {
            background-color: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 30px;
            position: sticky;
            top: 100px;
            transition: transform var(--transition-speed);
        }

        .order-summary:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        }

        .order-summary-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }

        .order-items {
            margin-bottom: 20px;
            max-height: 300px;
            overflow-y: auto;
            padding-right: 10px;
        }

        .order-item {
            display: flex;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .order-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .order-item-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            margin-right: 15px;
        }

        .order-item-details {
            flex: 1;
        }

        .order-item-title {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 5px;
            font-size: 14px;
        }

        .order-item-price {
            font-size: 14px;
            color: var(--primary-color);
            font-weight: 600;
        }

        .order-item-quantity {
            font-size: 13px;
            color: #777;
        }

        .order-totals {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        .order-total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .order-total-row.final {
            font-size: 18px;
            font-weight: 700;
            color: var(--secondary-color);
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
        }

        .order-total-row.final .amount {
            color: var(--primary-color);
        }

        .checkout-button {
            display: block;
            width: 100%;
            padding: 16px;
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            font-size: 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all var(--transition-speed);
            margin-top: 30px;
            text-align: center;
            box-shadow: 0 4px 10px rgba(255, 140, 0, 0.2);
        }

        .checkout-button:hover {
            background-color: #E07B00;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 140, 0, 0.25);
        }

        .secure-checkout {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 15px;
            font-size: 13px;
            color: #777;
        }

        .secure-checkout i {
            margin-right: 5px;
            color: var(--success-color);
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
            
            .header-actions {
                gap: 15px;
            }
            
            .checkout-form {
                padding: 30px 20px;
            }
            
            .order-summary {
                padding: 20px;
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
            
            .payment-method {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .payment-method-icon {
                margin-bottom: 10px;
            }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Payment method selection
            const paymentMethods = document.querySelectorAll('.payment-method');
            
            paymentMethods.forEach(method => {
                method.addEventListener('click', function() {
                    // Remove selected class from all methods
                    paymentMethods.forEach(m => m.classList.remove('selected'));
                    
                    // Add selected class to clicked method
                    this.classList.add('selected');
                    
                    // Check the radio button
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                });
            });
        });
    </script>
</head>
<body>

<header>
    <div class="header-container">
        <div class="logo">
            <a href="index.php">BANDER-SHOP</a>
        </div>

        <div class="header-actions">
            <a href="compte.php"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['user_nom']); ?></a>
            <a href="messages.php"><i class="fas fa-envelope"></i> Messages</a>
            <a href="panier.php"><i class="fas fa-shopping-cart"></i> <strong>Panier</strong></a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
            <a href="mes_articles.php" class="sell-button"><i class="fas fa-tag"></i> Vends tes articles</a>
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

<main>
    <h1 class="page-title">Finaliser votre commande</h1>

    <div class="checkout-container">
        <div class="checkout-form">
            <form method="POST" action="checkout.php">
                <div class="checkout-section">
                    <h2 class="checkout-section-title"><i class="fas fa-map-marker-alt"></i> Adresse de livraison</h2>
                    <div class="form-group">
                        <label for="adresse_livraison">Adresse complète</label>
                        <input type="text" id="adresse_livraison" name="adresse_livraison" class="form-control" value="<?= htmlspecialchars($user['adresse'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="checkout-section">
                    <h2 class="checkout-section-title"><i class="fas fa-credit-card"></i> Méthode de paiement</h2>
                    <div class="payment-methods">
                        <div class="payment-method selected">
                            <input type="radio" id="carte" name="methode_paiement" value="carte" checked>
                            <div class="payment-method-icon"><i class="fas fa-credit-card"></i></div>
                            <div class="payment-method-details">
                                <div class="payment-method-title">Carte bancaire</div>
                                <div class="payment-method-description">Paiement sécurisé par carte Visa, Mastercard ou American Express</div>
                            </div>
                        </div>

                        <div class="payment-method">
                            <input type="radio" id="paypal" name="methode_paiement" value="paypal">
                            <div class="payment-method-icon"><i class="fab fa-paypal"></i></div>
                            <div class="payment-method-details">
                                <div class="payment-method-title">PayPal</div>
                                <div class="payment-method-description">Paiement rapide et sécurisé avec votre compte PayPal</div>
                            </div>
                        </div>

                        <div class="payment-method">
                            <input type="radio" id="apple_pay" name="methode_paiement" value="apple_pay">
                            <div class="payment-method-icon"><i class="fab fa-apple-pay"></i></div>
                            <div class="payment-method-details">
                                <div class="payment-method-title">Apple Pay</div>
                                <div class="payment-method-description">Paiement simple et sécurisé avec Apple Pay</div>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="checkout-button">
                    <i class="fas fa-lock"></i> Confirmer la commande
                </button>
            </form>
        </div>

        <div class="order-summary">
            <h2 class="order-summary-title">Récapitulatif de la commande</h2>
            
            <div class="order-items">
                <?php foreach ($produits_panier as $produit): ?>
                    <div class="order-item">
                        <img src="<?= htmlspecialchars($produit['image_url']) ?>" alt="<?= htmlspecialchars($produit['titre']) ?>" class="order-item-image">
                        <div class="order-item-details">
                            <div class="order-item-title"><?= htmlspecialchars($produit['titre']) ?></div>
                            <div class="order-item-price"><?= number_format($produit['prix'], 2, ',', ' ') ?> €</div>
                            <div class="order-item-quantity">Quantité: <?= $produit['quantite'] ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="order-totals">
                <div class="order-total-row">
                    <div class="label">Sous-total</div>
                    <div class="amount"><?= number_format($panier_total, 2, ',', ' ') ?> €</div>
                </div>
                <div class="order-total-row">
                    <div class="label">Frais de livraison</div>
                    <div class="amount">Gratuit</div>
                </div>
                <div class="order-total-row final">
                    <div class="label">Total</div>
                    <div class="amount"><?= number_format($panier_total, 2, ',', ' ') ?> €</div>
                </div>
            </div>
            
            <div class="secure-checkout">
                <i class="fas fa-shield-alt"></i> Paiement 100% sécurisé
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