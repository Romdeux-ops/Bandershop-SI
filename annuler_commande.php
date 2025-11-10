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

// Vérifier si un ID de commande est fourni
if (!isset($_GET['commande_id']) || !is_numeric($_GET['commande_id'])) {
    $_SESSION['erreur'] = "Commande non valide.";
    header("Location: suivi_commande.php");
    exit();
}

$commande_id = intval($_GET['commande_id']);

// Vérifier que la commande appartient à l'utilisateur et est annulable
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND acheteur_id = ? AND statut = 'en_preparation'");
$stmt->execute([$commande_id, $_SESSION['user_id']]);
$commande = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$commande) {
    $_SESSION['erreur'] = "Cette commande ne peut pas être annulée (elle n'existe pas ou est déjà expédiée).";
    header("Location: suivi_commande.php");
    exit();
}

// Traitement de l'annulation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raison_annulation = htmlspecialchars($_POST['raison_annulation'] ?? '');

    if (empty($raison_annulation)) {
        $_SESSION['erreur'] = "Veuillez indiquer une raison pour l'annulation.";
    } else {
        // Mettre à jour le statut de la commande à "annulee"
        $stmt = $pdo->prepare("UPDATE orders SET statut = 'annulee' WHERE id = ?");
        $stmt->execute([$commande_id]);

        // Restaurer les produits au statut "disponible"
        $stmt = $pdo->prepare("
            UPDATE products p
            INNER JOIN order_products op ON p.id = op.product_id
            SET p.status = 'disponible'
            WHERE op.order_id = ?
        ");
        $stmt->execute([$commande_id]);

        // Mettre à jour les étapes de la commande
        $stmt = $pdo->prepare("
            UPDATE order_steps 
            SET statut = 'en_attente', date_etape = NOW()
            WHERE order_id = ? AND etape_nom NOT IN ('Commande confirmée', 'En préparation')
        ");
        $stmt->execute([$commande_id]);

        // Ajouter une étape "Commande annulée"
        $stmt = $pdo->prepare("
            INSERT INTO order_steps (order_id, etape_nom, date_etape, statut)
            VALUES (?, 'Commande annulée', NOW(), 'complete')
        ");
        $stmt->execute([$commande_id]);

        // Simuler un remboursement (dans un vrai site, cela intégrerait une API de paiement)
        $montant_rembourse = $commande['montant_total'];
        $_SESSION['message'] = "Votre commande #{$commande['numero_commande']} a été annulée avec succès. " .
                              "Un remboursement de " . number_format($montant_rembourse, 2, ',', ' ') . " € sera effectué " .
                              "sur votre méthode de paiement initiale dans un délai de 3 à 5 jours ouvrables.";
        header("Location: suivi_commande.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Annuler une commande - BANDER-SHOP</title>
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
            --warning-color: #f39c12;
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
            max-width: 800px;
            margin: 60px auto;
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
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .notification.error {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--error-color);
        }

        .notification.warning {
            background-color: rgba(243, 156, 18, 0.1);
            color: var(--warning-color);
        }

        /* Cancel form */
        .cancel-container {
            background-color: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 40px;
            transition: transform var(--transition-speed);
        }

        .cancel-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        }

        .cancel-info {
            margin-bottom: 30px;
            padding: 20px;
            background-color: rgba(243, 156, 18, 0.1);
            border-radius: var(--radius);
            border-left: 4px solid var(--warning-color);
        }

        .cancel-info h3 {
            color: var(--warning-color);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            font-size: 18px;
        }

        .cancel-info h3 i {
            margin-right: 10px;
        }

        .cancel-info p {
            font-size: 14px;
            margin-bottom: 10px;
        }

        .cancel-info ul {
            margin-left: 20px;
            font-size: 14px;
        }

        .cancel-info li {
            margin-bottom: 5px;
        }

        .form-group {
            margin-bottom: 25px;
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
            min-height: 120px;
            resize: vertical;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 140, 0, 0.1);
        }

        .form-hint {
            display: block;
            margin-top: 6px;
            font-size: 12px;
            color: #777;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .cancel-button {
            flex: 1;
            padding: 16px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all var(--transition-speed);
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--error-color);
            color: white;
            box-shadow: 0 4px 10px rgba(231, 76, 60, 0.2);
        }

        .cancel-button:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(231, 76, 60, 0.25);
        }

        .cancel-button i {
            margin-right: 8px;
        }

        .back-button {
            flex: 1;
            padding: 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all var(--transition-speed);
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: white;
            color: var(--secondary-color);
            text-decoration: none;
        }

        .back-button:hover {
            background-color: var(--light-gray);
            transform: translateY(-2px);
        }

        .back-button i {
            margin-right: 8px;
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
            
            .cancel-container {
                padding: 30px 20px;
            }
            
            .button-group {
                flex-direction: column;
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
                margin: 30px auto;
            }
            
            .page-title {
                font-size: 24px;
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
            <a href="compte.php"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['user_nom']); ?></a>
            <a href="messages.php"><i class="fas fa-envelope"></i> Messages</a>
            <a href="panier.php"><i class="fas fa-shopping-cart"></i> Panier</a>
            <a href="mes_articles.php"><i class="fas fa-box"></i> Mes Articles</a>
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
    <h1 class="page-title">Annuler la commande #<?= htmlspecialchars($commande['numero_commande']) ?></h1>

    <?php if (isset($_SESSION['erreur'])): ?>
        <div class="notification error">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['erreur']) ?>
            <?php unset($_SESSION['erreur']); ?>
        </div>
    <?php endif; ?>

    <div class="cancel-container">
        <div class="cancel-info">
            <h3><i class="fas fa-exclamation-triangle"></i> Informations importantes</h3>
            <p>Vous êtes sur le point d'annuler votre commande. Veuillez noter que :</p>
            <ul>
                <li>L'annulation est définitive et ne peut pas être inversée.</li>
                <li>Le remboursement sera effectué sur votre méthode de paiement initiale.</li>
                <li>Le délai de remboursement est généralement de 3 à 5 jours ouvrables.</li>
                <li>Seules les commandes en préparation peuvent être annulées.</li>
            </ul>
        </div>

        <form method="POST" action="annuler_commande.php?commande_id=<?= $commande_id ?>">
            <div class="form-group">
                <label for="raison_annulation">Raison de l'annulation</label>
                <textarea id="raison_annulation" name="raison_annulation" class="form-control" required placeholder="Veuillez nous indiquer la raison de l'annulation de votre commande..."></textarea>
                <span class="form-hint">Cette information nous aide à améliorer nos services.</span>
            </div>

            <div class="button-group">
                <a href="suivi_commande.php" class="back-button">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
                <button type="submit" class="cancel-button">
                    <i class="fas fa-times-circle"></i> Confirmer l'annulation
                </button>
            </div>
        </form>
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