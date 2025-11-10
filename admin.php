<?php
session_start();

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    $_SESSION['erreur'] = "Accès refusé. Vous devez être administrateur pour accéder à cette page.";
    header("Location: index.php");
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

// Traitement des actions
$message = '';
$error = '';

// Supprimer un produit
if (isset($_POST['delete_product']) && isset($_POST['product_id'])) {
    $product_id = intval($_POST['product_id']);
    
    try {
        // Vérifier si le produit est lié à des commandes
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM order_products WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            // Si le produit est lié à des commandes, on le marque comme supprimé au lieu de le supprimer
            $stmt = $pdo->prepare("UPDATE products SET status = 'supprime' WHERE id = ?");
            $stmt->execute([$product_id]);
            $message = "Le produit a été marqué comme supprimé.";
        } else {
            // Si le produit n'est pas lié à des commandes, on peut le supprimer complètement
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $message = "Le produit a été supprimé avec succès.";
        }
    } catch (PDOException $e) {
        $error = "Erreur lors de la suppression du produit : " . $e->getMessage();
    }
}

// Mettre à jour le statut d'une commande
if (isset($_POST['update_order']) && isset($_POST['order_id']) && isset($_POST['new_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['new_status'];
    
    // Valider le statut
    $valid_statuses = ['en_preparation', 'expedie', 'en_livraison', 'livre', 'annulee'];
    if (!in_array($new_status, $valid_statuses)) {
        $error = "Statut de commande non valide.";
    } else {
        try {
            // Mettre à jour le statut de la commande
            $stmt = $pdo->prepare("UPDATE orders SET statut = ? WHERE id = ?");
            $stmt->execute([$new_status, $order_id]);
            
            // Mettre à jour les étapes de suivi en fonction du nouveau statut
            if ($new_status == 'expedie') {
                $stmt = $pdo->prepare("UPDATE order_steps SET statut = 'complete', date_etape = NOW() WHERE order_id = ? AND etape_nom = 'Expédiée'");
                $stmt->execute([$order_id]);
            } elseif ($new_status == 'en_livraison') {
                $stmt = $pdo->prepare("UPDATE order_steps SET statut = 'complete', date_etape = NOW() WHERE order_id = ? AND etape_nom IN ('Expédiée', 'En livraison')");
                $stmt->execute([$order_id]);
            } elseif ($new_status == 'livre') {
                $stmt = $pdo->prepare("UPDATE order_steps SET statut = 'complete', date_etape = NOW() WHERE order_id = ? AND etape_nom IN ('Expédiée', 'En livraison', 'Livrée')");
                $stmt->execute([$order_id]);
            }
            
            $message = "Le statut de la commande a été mis à jour avec succès.";
        } catch (PDOException $e) {
            $error = "Erreur lors de la mise à jour du statut de la commande : " . $e->getMessage();
        }
    }
}

// Récupérer les produits
$stmt = $pdo->query("
    SELECT p.*, c.nom AS categorie_nom, u.nom AS vendeur_nom, u.prenom AS vendeur_prenom 
    FROM products p
    LEFT JOIN categories c ON p.categorie_id = c.id
    LEFT JOIN users u ON p.vendeur_id = u.id
    WHERE p.status != 'supprime' OR p.status IS NULL
    ORDER BY p.date_ajout DESC
");
$products = $stmt->fetchAll();

// Récupérer les commandes
$stmt = $pdo->query("
    SELECT o.*, u.nom AS acheteur_nom, u.prenom AS acheteur_prenom, 
           COUNT(op.id) AS nombre_produits
    FROM orders o
    LEFT JOIN users u ON o.acheteur_id = u.id
    LEFT JOIN order_products op ON o.id = op.order_id
    GROUP BY o.id
    ORDER BY o.date_commande DESC
");
$orders = $stmt->fetchAll();

// Récupérer les statistiques
$stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE status != 'supprime' OR status IS NULL");
$total_products = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM orders");
$total_orders = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$total_users = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT SUM(montant_total) FROM orders WHERE statut != 'annulee'");
$total_sales = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - BANDER-SHOP</title>
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
            --info-color: #3498db;
            --admin-sidebar-width: 260px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--light-gray);
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

        .admin-badge {
            background-color: var(--primary-color);
            color: white !important;
            padding: 5px 10px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }

        /* Admin Layout */
        .admin-container {
            display: flex;
            min-height: calc(100vh - 70px);
        }

        /* Sidebar */
        .admin-sidebar {
            width: var(--admin-sidebar-width);
            background-color: var(--background-color);
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.03);
            padding: 30px 0;
            position: fixed;
            height: calc(100vh - 70px);
            overflow-y: auto;
        }

        .admin-sidebar-header {
            padding: 0 25px 20px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 20px;
        }

        .admin-sidebar-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--secondary-color);
            display: flex;
            align-items: center;
        }

        .admin-sidebar-title i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        .admin-nav {
            list-style: none;
        }

        .admin-nav-item {
            margin-bottom: 5px;
        }

        .admin-nav-link {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            text-decoration: none;
            color: var(--text-color);
            font-weight: 500;
            transition: all var(--transition-speed);
            border-left: 3px solid transparent;
        }

        .admin-nav-link:hover {
            background-color: var(--light-gray);
            color: var(--primary-color);
        }

        .admin-nav-link.active {
            background-color: var(--light-gray);
            color: var(--primary-color);
            border-left: 3px solid var(--primary-color);
        }

        .admin-nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .admin-content {
            flex: 1;
            padding: 30px;
            margin-left: var(--admin-sidebar-width);
        }

        .admin-header {
            margin-bottom: 30px;
        }

        .admin-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 10px;
        }

        .admin-subtitle {
            color: var(--text-color);
            font-size: 14px;
        }

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: var(--background-color);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 25px;
            transition: transform var(--transition-speed);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 24px;
            color: white;
        }

        .stat-icon.products {
            background-color: var(--primary-color);
        }

        .stat-icon.orders {
            background-color: var(--info-color);
        }

        .stat-icon.users {
            background-color: var(--success-color);
        }

        .stat-icon.sales {
            background-color: var(--warning-color);
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }

        .stat-label {
            color: var(--text-color);
            font-size: 14px;
        }

        /* Tabs */
        .admin-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 30px;
        }

        .admin-tab {
            padding: 15px 25px;
            font-weight: 600;
            color: var(--text-color);
            cursor: pointer;
            transition: all var(--transition-speed);
            border-bottom: 3px solid transparent;
        }

        .admin-tab:hover {
            color: var(--primary-color);
        }

        .admin-tab.active {
            color: var(--primary-color);
            border-bottom: 3px solid var(--primary-color);
        }

        /* Tables */
        .admin-table-container {
            background-color: var(--background-color);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }

        .admin-table th {
            background-color: var(--light-gray);
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            color: var(--secondary-color);
            border-bottom: 1px solid var(--border-color);
        }

        .admin-table td {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .admin-table tr:last-child td {
            border-bottom: none;
        }

        .admin-table tr:hover {
            background-color: var(--light-gray);
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
        }

        .status-badge.available {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .status-badge.sold {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--info-color);
        }

        .status-badge.pending {
            background-color: rgba(243, 156, 18, 0.1);
            color: var(--warning-color);
        }

        .status-badge.shipped {
            background-color: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
        }

        .status-badge.delivered {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .status-badge.cancelled {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--error-color);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-speed);
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .action-btn i {
            margin-right: 5px;
        }

        .action-btn.view {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--info-color);
        }

        .action-btn.view:hover {
            background-color: rgba(52, 152, 219, 0.2);
        }

        .action-btn.edit {
            background-color: rgba(243, 156, 18, 0.1);
            color: var(--warning-color);
        }

        .action-btn.edit:hover {
            background-color: rgba(243, 156, 18, 0.2);
        }

        .action-btn.delete {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--error-color);
        }

        .action-btn.delete:hover {
            background-color: rgba(231, 76, 60, 0.2);
        }

        /* Forms */
        .admin-form {
            background-color: var(--background-color);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 25px;
            margin-bottom: 30px;
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            flex: 1;
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
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: all var(--transition-speed);
            background-color: var(--light-gray);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 140, 0, 0.1);
        }

        /* Notifications */
        .notification {
            padding: 15px 20px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
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

        /* Modal */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all var(--transition-speed);
        }

        .modal-backdrop.active {
            opacity: 1;
            visibility: visible;
        }

        .modal {
            background-color: var(--background-color);
            border-radius: var(--radius);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            transform: translateY(-20px);
            transition: all var(--transition-speed);
        }

        .modal-backdrop.active .modal {
            transform: translateY(0);
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--secondary-color);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: var(--text-color);
            transition: color var(--transition-speed);
        }

        .modal-close:hover {
            color: var(--error-color);
        }

        .modal-body {
            padding: 25px;
        }

        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all var(--transition-speed);
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: #E07B00;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background-color: var(--light-gray);
            color: var(--text-color);
        }

        .btn-secondary:hover {
            background-color: var(--border-color);
            transform: translateY(-2px);
        }

        .btn-danger {
            background-color: var(--error-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }

        .pagination-item {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background-color: var(--background-color);
            color: var(--text-color);
            font-weight: 600;
            transition: all var(--transition-speed);
            cursor: pointer;
            text-decoration: none;
        }

        .pagination-item:hover {
            background-color: var(--light-gray);
            color: var(--primary-color);
        }

        .pagination-item.active {
            background-color: var(--primary-color);
            color: white;
        }

        /* Tab Content */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .admin-sidebar {
                width: 70px;
                overflow: visible;
            }

            .admin-sidebar-title span,
            .admin-nav-link span {
                display: none;
            }

            .admin-nav-link i {
                margin-right: 0;
                font-size: 18px;
            }

            .admin-content {
                margin-left: 70px;
            }

            .admin-sidebar-header {
                padding: 0 0 20px;
                text-align: center;
            }

            .admin-sidebar-title i {
                margin-right: 0;
            }

            .admin-nav-link {
                justify-content: center;
                padding: 15px 0;
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
            
            .admin-content {
                padding: 20px;
            }
            
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .admin-table {
                display: block;
                overflow-x: auto;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
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
            
            .admin-content {
                padding: 15px;
                margin-left: 0;
            }
            
            .admin-sidebar {
                position: fixed;
                bottom: 0;
                left: 0;
                width: 100%;
                height: auto;
                z-index: 100;
                padding: 10px 0;
            }
            
            .admin-sidebar-header {
                display: none;
            }
            
            .admin-nav {
                display: flex;
                justify-content: space-around;
            }
            
            .admin-nav-item {
                margin-bottom: 0;
            }
            
            .admin-nav-link {
                padding: 10px;
                border-left: none;
                flex-direction: column;
                font-size: 10px;
            }
            
            .admin-nav-link i {
                margin-right: 0;
                margin-bottom: 5px;
            }
            
            .admin-nav-link span {
                display: block;
                font-size: 10px;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tabs functionality
            const tabs = document.querySelectorAll('.admin-tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    // Remove active class from all tabs
                    tabs.forEach(t => t.classList.remove('active'));
                    
                    // Add active class to clicked tab
                    tab.classList.add('active');
                    
                    // Hide all tab contents
                    tabContents.forEach(content => {
                        content.classList.remove('active');
                    });
                    
                    // Show the corresponding tab content
                    const tabId = tab.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
            
            // Modal functionality
            const modalTriggers = document.querySelectorAll('[data-modal]');
            const modalBackdrops = document.querySelectorAll('.modal-backdrop');
            const modalCloses = document.querySelectorAll('.modal-close');
            
            modalTriggers.forEach(trigger => {
                trigger.addEventListener('click', () => {
                    const modalId = trigger.getAttribute('data-modal');
                    document.getElementById(modalId).classList.add('active');
                });
            });
            
            modalCloses.forEach(close => {
                close.addEventListener('click', () => {
                    const modal = close.closest('.modal-backdrop');
                    modal.classList.remove('active');
                });
            });
            
            modalBackdrops.forEach(backdrop => {
                backdrop.addEventListener('click', (e) => {
                    if (e.target === backdrop) {
                        backdrop.classList.remove('active');
                    }
                });
            });
            
            // Confirm delete
            const deleteButtons = document.querySelectorAll('.delete-product-btn');
            
            deleteButtons.forEach(button => {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (confirm('Êtes-vous sûr de vouloir supprimer ce produit ?')) {
                        const form = button.closest('form');
                        form.submit();
                    }
                });
            });
            
            // Order status update
            const statusSelects = document.querySelectorAll('.order-status-select');
            
            statusSelects.forEach(select => {
                select.addEventListener('change', () => {
                    const form = select.closest('form');
                    form.submit();
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
            <a href="admin.php"><i class="fas fa-tachometer-alt"></i> Dashboard <span class="admin-badge">Admin</span></a>
            <a href="compte.php"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['user_nom']); ?></a>
            <a href="messages.php"><i class="fas fa-envelope"></i> Messages</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </div>
    </div>
</header>

<div class="admin-container">
    <!-- Sidebar -->
    <div class="admin-sidebar">
        <div class="admin-sidebar-header">
            <div class="admin-sidebar-title">
                <i class="fas fa-tachometer-alt"></i>
                <span>Administration</span>
            </div>
        </div>

        <ul class="admin-nav">
            <li class="admin-nav-item">
                <a href="#" class="admin-nav-link active" data-tab="dashboard-tab">
                    <i class="fas fa-home"></i>
                    <span>Tableau de bord</span>
                </a>
            </li>
            <li class="admin-nav-item">
                <a href="#" class="admin-nav-link" data-tab="products-tab">
                    <i class="fas fa-box"></i>
                    <span>Produits</span>
                </a>
            </li>
            <li class="admin-nav-item">
                <a href="#" class="admin-nav-link" data-tab="orders-tab">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Commandes</span>
                </a>
            </li>
            <li class="admin-nav-item">
                <a href="#" class="admin-nav-link" data-tab="users-tab">
                    <i class="fas fa-users"></i>
                    <span>Utilisateurs</span>
                </a>
            </li>
            <li class="admin-nav-item">
                <a href="#" class="admin-nav-link" data-tab="settings-tab">
                    <i class="fas fa-cog"></i>
                    <span>Paramètres</span>
                </a>
            </li>
            <li class="admin-nav-item">
                <a href="index.php" class="admin-nav-link">
                    <i class="fas fa-arrow-left"></i>
                    <span>Retour au site</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="admin-content">
        <!-- Notifications -->
        <?php if (!empty($message)): ?>
            <div class="notification success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="notification error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Dashboard Tab -->
        <div id="dashboard-tab" class="tab-content active">
            <div class="admin-header">
                <h1 class="admin-title">Tableau de bord</h1>
                <p class="admin-subtitle">Bienvenue dans l'interface d'administration de BANDER-SHOP</p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon products">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-value"><?= number_format($total_products) ?></div>
                    <div class="stat-label">Produits</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orders">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-value"><?= number_format($total_orders) ?></div>
                    <div class="stat-label">Commandes</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?= number_format($total_users) ?></div>
                    <div class="stat-label">Utilisateurs</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon sales">
                        <i class="fas fa-euro-sign"></i>
                    </div>
                    <div class="stat-value"><?= number_format($total_sales, 2, ',', ' ') ?> €</div>
                    <div class="stat-label">Ventes totales</div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="admin-header">
                <h2 class="admin-title">Commandes récentes</h2>
            </div>

            <div class="admin-table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>N° Commande</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Montant</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $count = 0;
                        foreach ($orders as $order): 
                            if ($count >= 5) break; // Limiter à 5 commandes récentes
                            $count++;
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($order['numero_commande']) ?></td>
                                <td><?= htmlspecialchars($order['acheteur_prenom'] . ' ' . $order['acheteur_nom']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($order['date_commande'])) ?></td>
                                <td><?= number_format($order['montant_total'], 2, ',', ' ') ?> €</td>
                                <td>
                                    <?php
                                    $status_class = '';
                                    $status_text = '';
                                    
                                    switch ($order['statut']) {
                                        case 'en_preparation':
                                            $status_class = 'pending';
                                            $status_text = 'En préparation';
                                            break;
                                        case 'expedie':
                                            $status_class = 'shipped';
                                            $status_text = 'Expédiée';
                                            break;
                                        case 'en_livraison':
                                            $status_class = 'shipped';
                                            $status_text = 'En livraison';
                                            break;
                                        case 'livre':
                                            $status_class = 'delivered';
                                            $status_text = 'Livrée';
                                            break;
                                        case 'annulee':
                                            $status_class = 'cancelled';
                                            $status_text = 'Annulée';
                                            break;
                                        default:
                                            $status_class = 'pending';
                                            $status_text = 'En attente';
                                    }
                                    ?>
                                    <span class="status-badge <?= $status_class ?>"><?= $status_text ?></span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn view" data-modal="view-order-modal-<?= $order['id'] ?>">
                                            <i class="fas fa-eye"></i> Voir
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Products Tab -->
        <div id="products-tab" class="tab-content">
            <div class="admin-header">
                <h1 class="admin-title">Gestion des produits</h1>
                <p class="admin-subtitle">Gérez tous les produits disponibles sur la plateforme</p>
            </div>

            <div class="admin-table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Produit</th>
                            <th>Catégorie</th>
                            <th>Prix</th>
                            <th>Vendeur</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?= $product['id'] ?></td>
                                <td><?= htmlspecialchars($product['titre']) ?></td>
                                <td><?= htmlspecialchars($product['categorie_nom'] ?? 'Non catégorisé') ?></td>
                                <td><?= number_format($product['prix'], 2, ',', ' ') ?> €</td>
                                <td><?= htmlspecialchars($product['vendeur_prenom'] . ' ' . $product['vendeur_nom']) ?></td>
                                <td>
                                    <?php
                                    $status_class = '';
                                    $status_text = '';
                                    
                                    switch ($product['status']) {
                                        case 'vendu':
                                            $status_class = 'sold';
                                            $status_text = 'Vendu';
                                            break;
                                        case 'supprime':
                                            $status_class = 'cancelled';
                                            $status_text = 'Supprimé';
                                            break;
                                        default:
                                            $status_class = 'available';
                                            $status_text = 'Disponible';
                                    }
                                    ?>
                                    <span class="status-badge <?= $status_class ?>"><?= $status_text ?></span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="produit.php?id=<?= $product['id'] ?>" class="action-btn view">
                                            <i class="fas fa-eye"></i> Voir
                                        </a>
                                        <form method="POST" action="admin.php" style="display: inline;">
                                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                            <button type="submit" name="delete_product" class="action-btn delete delete-product-btn">
                                                <i class="fas fa-trash"></i> Supprimer
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Orders Tab -->
        <div id="orders-tab" class="tab-content">
            <div class="admin-header">
                <h1 class="admin-title">Gestion des commandes</h1>
                <p class="admin-subtitle">Suivez et gérez toutes les commandes de la plateforme</p>
            </div>

            <div class="admin-table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>N° Commande</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Montant</th>
                            <th>Produits</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?= htmlspecialchars($order['numero_commande']) ?></td>
                                <td><?= htmlspecialchars($order['acheteur_prenom'] . ' ' . $order['acheteur_nom']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($order['date_commande'])) ?></td>
                                <td><?= number_format($order['montant_total'], 2, ',', ' ') ?> €</td>
                                <td><?= $order['nombre_produits'] ?></td>
                                <td>
                                    <form method="POST" action="admin.php">
                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                        <select name="new_status" class="form-control order-status-select" style="width: auto; padding: 8px;">
                                            <option value="en_preparation" <?= $order['statut'] == 'en_preparation' ? 'selected' : '' ?>>En préparation</option>
                                            <option value="expedie" <?= $order['statut'] == 'expedie' ? 'selected' : '' ?>>Expédiée</option>
                                            <option value="en_livraison" <?= $order['statut'] == 'en_livraison' ? 'selected' : '' ?>>En livraison</option>
                                            <option value="livre" <?= $order['statut'] == 'livre' ? 'selected' : '' ?>>Livrée</option>
                                            <option value="annulee" <?= $order['statut'] == 'annulee' ? 'selected' : '' ?>>Annulée</option>
                                        </select>
                                        <input type="hidden" name="update_order" value="1">
                                    </form>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn view" data-modal="view-order-modal-<?= $order['id'] ?>">
                                            <i class="fas fa-eye"></i> Détails
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Users Tab -->
        <div id="users-tab" class="tab-content">
            <div class="admin-header">
                <h1 class="admin-title">Gestion des utilisateurs</h1>
                <p class="admin-subtitle">Gérez les comptes utilisateurs de la plateforme</p>
            </div>

            <div class="admin-form">
                <p>Cette section est en cours de développement. Elle permettra bientôt de gérer les utilisateurs, leurs rôles et leurs permissions.</p>
            </div>
        </div>

        <!-- Settings Tab -->
        <div id="settings-tab" class="tab-content">
            <div class="admin-header">
                <h1 class="admin-title">Paramètres</h1>
                <p class="admin-subtitle">Configurez les paramètres de la plateforme</p>
            </div>

            <div class="admin-form">
                <p>Cette section est en cours de développement. Elle permettra bientôt de configurer les paramètres généraux de la plateforme.</p>
            </div>
        </div>
    </div>
</div>

<!-- Order View Modals -->
<?php foreach ($orders as $order): ?>
    <div id="view-order-modal-<?= $order['id'] ?>" class="modal-backdrop">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Commande #<?= htmlspecialchars($order['numero_commande']) ?></h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <p><strong>Client:</strong> <?= htmlspecialchars($order['acheteur_prenom'] . ' ' . $order['acheteur_nom']) ?></p>
                <p><strong>Date:</strong> <?= date('d/m/Y H:i', strtotime($order['date_commande'])) ?></p>
                <p><strong>Montant:</strong> <?= number_format($order['montant_total'], 2, ',', ' ') ?> €</p>
                <p><strong>Adresse de livraison:</strong> <?= htmlspecialchars($order['adresse_livraison']) ?></p>
                <p><strong>Méthode de paiement:</strong> <?= htmlspecialchars($order['methode_paiement']) ?></p>
                
                <h4 style="margin-top: 20px; margin-bottom: 10px;">Produits</h4>
                <?php
                $stmt = $pdo->prepare("
                    SELECT op.*, p.titre, p.image_url 
                    FROM order_products op
                    LEFT JOIN products p ON op.product_id = p.id
                    WHERE op.order_id = ?
                ");
                $stmt->execute([$order['id']]);
                $orderProducts = $stmt->fetchAll();
                ?>
                
                <div style="max-height: 200px; overflow-y: auto; margin-bottom: 20px;">
                    <?php foreach ($orderProducts as $product): ?>
                        <div style="display: flex; align-items: center; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid var(--border-color);">
                            <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['titre']) ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px; margin-right: 10px;">
                            <div>
                                <div style="font-weight: 600;"><?= htmlspecialchars($product['titre']) ?></div>
                                <div style="font-size: 14px; color: var(--text-color);">
                                    <?= $product['quantite'] ?> x <?= number_format($product['prix_unitaire'], 2, ',', ' ') ?> €
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <h4 style="margin-top: 20px; margin-bottom: 10px;">Suivi de commande</h4>
                <?php
                $stmt = $pdo->prepare("
                    SELECT * FROM order_steps
                    WHERE order_id = ?
                    ORDER BY id ASC
                ");
                $stmt->execute([$order['id']]);
                $orderSteps = $stmt->fetchAll();
                ?>
                
                <div style="margin-bottom: 20px;">
                    <?php foreach ($orderSteps as $step): ?>
                        <div style="display: flex; align-items: center; margin-bottom: 10px;">
                            <div style="width: 20px; height: 20px; border-radius: 50%; margin-right: 10px; 
                                        background-color: <?= $step['statut'] == 'complete' ? 'var(--success-color)' : 'var(--border-color)' ?>; 
                                        display: flex; align-items: center; justify-content: center; color: white; font-size: 10px;">
                                <?= $step['statut'] == 'complete' ? '<i class="fas fa-check"></i>' : '' ?>
                            </div>
                            <div>
                                <div style="font-weight: 600;"><?= htmlspecialchars($step['etape_nom']) ?></div>
                                <?php if ($step['statut'] == 'complete'): ?>
                                    <div style="font-size: 12px; color: var(--text-color);">
                                        <?= date('d/m/Y H:i', strtotime($step['date_etape'])) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary modal-close">Fermer</button>
            </div>
        </div>
    </div>
<?php endforeach; ?>

</body>
</html>