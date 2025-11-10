<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

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

$erreur = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $titre = $_POST['titre'] ?? '';
    $description = $_POST['description'] ?? '';
    $prix = $_POST['prix'] ?? 0;
    $categorie_id = $_POST['categorie_id'] ?? 0;
    $vendeur_id = $_SESSION['user_id'];

    // Vérifier et gérer l'upload de l'image
    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        // Trouver le prochain numéro de dossier
        $stmt = $pdo->query("SELECT MAX(id) AS max_id FROM products");
        $result = $stmt->fetch();
        $next_id = ($result['max_id'] + 1);

        $upload_dir = "images/produits/" . $next_id . "/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $image_name = basename($_FILES['image']['name']);
        $image_path = $upload_dir . $image_name;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
            // Image uploadée avec succès
        } else {
            $erreur = "Erreur lors du téléchargement de l'image.";
        }
    }

    if (!empty($titre) && !empty($description) && $prix > 0 && $categorie_id > 0) {
        // Ajouter le statut disponible par défaut
        $stmt = $pdo->prepare("INSERT INTO products (titre, description, prix, vendeur_id, image_url, categorie_id, status) VALUES (?, ?, ?, ?, ?, ?, 'disponible')");
        $stmt->execute([$titre, $description, $prix, $vendeur_id, $image_path, $categorie_id]);

        header("Location: mes_articles.php");
        exit();
    } else {
        $erreur = "Veuillez remplir tous les champs, assurez-vous que le prix est positif et sélectionnez une catégorie.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un Article - BANDER-SHOP</title>
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
            transform: translate140,0,0.2);
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

        /* Form container */
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 40px;
            transition: transform var(--transition-speed);
        }

        .form-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--secondary-color);
            font-size: 15px;
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
            box-shadow: 0 0 0 3px rgba(255, 140, 0, 0.1);
            outline: none;
        }

        textarea.form-control {
            min-height: 180px;
            resize: vertical;
        }

        .form-hint {
            display: block;
            margin-top: 8px;
            font-size: 13px;
            color: #777;
        }

        .file-upload {
            position: relative;
            overflow: hidden;
            margin-top: 15px;
            display: inline-block;
        }

        .file-upload input[type="file"] {
            font-size: 100px;
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            cursor: pointer;
            height: 100%;
            width: 100%;
        }

        .upload-btn {
            display: inline-flex;
            align-items: center;
            background-color: var(--light-gray);
            color: var(--secondary-color);
            border: 1px solid var(--border-color);
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            transition: all var(--transition-speed);
        }

        .upload-btn i {
            margin-right: 8px;
        }

        .upload-btn:hover {
            background-color: var(--border-color);
            color: var(--primary-color);
        }

        .file-name {
            margin-left: 15px;
            font-size: 14px;
            color: var(--secondary-color);
        }

        .submit-button {
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
            box-shadow: 0 4px 10px rgba(255, 140, 0, 0.2);
        }

        .submit-button:hover {
            background-color: #E07B00;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 140, 0, 0.25);
        }

        .error-message {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--error-color);
            padding: 15px;
            border-radius: var(--radius);
            margin-bottom: 25px;
            text-align: center;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .error-message i {
            margin-right: 8px;
            font-size: 16px;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 25px;
            color: var(--primary-color);
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            transition: all var(--transition-speed);
        }

        .back-link:hover {
            color: #E07B00;
            transform: translateX(-3px);
        }

        .back-link i {
            margin-right: 5px;
        }

        /* Preview image */
        .image-preview {
            margin-top: 15px;
            display: none;
            text-align: center;
        }

        .image-preview img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            box-shadow: var(--shadow);
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
            
            .form-container {
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
            
            .form-control {
                padding: 12px 14px;
            }
            
            .submit-button {
                padding: 14px;
            }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Afficher le nom du fichier sélectionné
            const fileInput = document.getElementById('image');
            const fileNameDisplay = document.getElementById('file-name');
            const imagePreview = document.getElementById('image-preview');
            const previewImg = document.getElementById('preview-img');

            fileInput.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    const file = this.files[0];
                    fileNameDisplay.textContent = file.name;
                    
                    // Afficher l'aperçu de l'image
                    if (file.type.match('image.*')) {
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            previewImg.src = e.target.result;
                            imagePreview.style.display = 'block';
                        }
                        
                        reader.readAsDataURL(file);
                    }
                } else {
                    fileNameDisplay.textContent = '';
                    imagePreview.style.display = 'none';
                }
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
    <h1 class="page-title">Ajouter un Nouvel Article</h1>

    <div class="form-container">
        <?php if (!empty($erreur)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($erreur) ?>
            </div>
        <?php endif; ?>

        <form action="ajouter_produit.php" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="titre">Titre de l'article</label>
                <input type="text" class="form-control" name="titre" id="titre" required>
                <span class="form-hint">Donnez un titre clair et descriptif à votre article</span>
            </div>

            <div class="form-group">
                <label for="description">Description détaillée</label>
                <textarea class="form-control" name="description" id="description" required></textarea>
                <span class="form-hint">Décrivez votre article, son état, ses caractéristiques, ses dimensions, etc.</span>
            </div>

            <div class="form-group">
                <label for="prix">Prix (€)</label>
                <input type="number" class="form-control" name="prix" id="prix" step="0.01" min="0.01" required>
                <span class="form-hint">Indiquez le prix en euros (minimum 0.01€)</span>
            </div>

            <div class="form-group">
                <label for="categorie_id">Catégorie</label>
                <select class="form-control" name="categorie_id" id="categorie_id" required>
                    <option value="">Sélectionnez une catégorie</option>
                    <?php foreach ($categories_filtrees as $categorie): ?>
                        <option value="<?= $categorie['id'] ?>"><?= htmlspecialchars($categorie['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="form-hint">Choisissez la catégorie qui correspond le mieux à votre article</span>
            </div>

            <div class="form-group">
                <label for="image">Photo de l'article</label>
                <div class="file-upload">
                    <div class="upload-btn"><i class="fas fa-camera"></i> Choisir une image</div>
                    <input type="file" name="image" id="image" accept="image/*" required>
                </div>
                <span id="file-name" class="file-name"></span>
                <span class="form-hint">Format recommandé : JPG, PNG. Taille maximale : 5 Mo</span>
                
                <div id="image-preview" class="image-preview">
                    <img id="preview-img" src="#" alt="Aperçu de l'image">
                </div>
            </div>

            <button type="submit" class="submit-button"><i class="fas fa-plus-circle"></i> Publier l'article</button>
        </form>

        <a href="mes_articles.php" class="back-link"><i class="fas fa-arrow-left"></i> Retour à mes articles</a>
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