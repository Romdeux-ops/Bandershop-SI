<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

// Vérifier si l'ID du produit est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: mes_articles.php");
    exit();
}

$product_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Connexion à la base de données
try {
    $pdo = new PDO("mysql:host=localhost;dbname=e_boutique", "root", "rootroot");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer les catégories pour le formulaire et la navigation
$stmt = $pdo->query("SELECT * FROM categories");
$categories = $stmt->fetchAll();

// Filtrer les catégories pour ne pas afficher Homme et Femme
$categories_filtrees = array_filter($categories, function($category) {
    return $category['nom'] !== 'Homme' && $category['nom'] !== 'Femme';
});

// Récupérer les informations du produit et vérifier que l'utilisateur en est bien le propriétaire
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND vendeur_id = ?");
$stmt->execute([$product_id, $user_id]);
$produit = $stmt->fetch();

// Si le produit n'existe pas ou n'appartient pas à l'utilisateur, rediriger
if (!$produit) {
    header("Location: mes_articles.php");
    exit();
}

// Initialiser les variables d'erreur et de succès
$errors = [];
$success = false;

// Traiter le formulaire s'il est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer et valider les données du formulaire
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $prix = trim($_POST['prix'] ?? '');
    $categorie_id = $_POST['categorie_id'] ?? '';
    $image_url = trim($_POST['image_url'] ?? '');

    // Validation du titre
    if (empty($titre)) {
        $errors['titre'] = "Le titre est requis";
    } elseif (strlen($titre) > 255) {
        $errors['titre'] = "Le titre ne doit pas dépasser 255 caractères";
    }

    // Validation de la description
    if (empty($description)) {
        $errors['description'] = "La description est requise";
    }

    // Validation du prix
    if (empty($prix)) {
        $errors['prix'] = "Le prix est requis";
    } elseif (!is_numeric($prix) || $prix <= 0) {
        $errors['prix'] = "Le prix doit être un nombre positif";
    }

    // Validation de la catégorie
    if (empty($categorie_id)) {
        $errors['categorie_id'] = "La catégorie est requise";
    }

    // Validation de l'URL de l'image
    if (empty($image_url)) {
        $errors['image_url'] = "L'URL de l'image est requise";
    }

    // Si un fichier image est uploadé, le traiter
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['image']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);

        // Vérifier l'extension
        if (!in_array(strtolower($filetype), $allowed)) {
            $errors['image'] = "Seuls les fichiers JPG, JPEG, PNG et GIF sont autorisés";
        } else {
            // Créer le dossier d'images s'il n'existe pas
            $upload_dir = 'image/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Générer un nom de fichier unique
            $new_filename = uniqid('product_') . '.' . $filetype;
            $destination = $upload_dir . $new_filename;

            // Déplacer le fichier uploadé
            if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                $image_url = $destination;
            } else {
                $errors['image'] = "Un problème est survenu lors de l'upload";
            }
        }
    }

    // S'il n'y a pas d'erreurs, mettre à jour le produit
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE products SET titre = ?, description = ?, prix = ?, categorie_id = ?, image_url = ? WHERE id = ? AND vendeur_id = ?");
            $stmt->execute([$titre, $description, $prix, $categorie_id, $image_url, $product_id, $user_id]);

            $success = true;

            // Rediriger après 2 secondes
            header("refresh:2;url=mes_articles.php");
        } catch (PDOException $e) {
            $errors['db'] = "Erreur lors de la mise à jour du produit: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un article - BANDER-SHOP</title>
    <style>
        :root {
            --primary-color: #FF8C00;
            --secondary-color: #FFA500;
            --accent-color: #FFD700;
            --text-color: #333;
            --light-background: #FFF8E7;
            --border-color: #FFEFD5;
            --error-color: #e74c3c;
            --success-color: #2ecc71;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background-color: #fff;
            color: var(--text-color);
            line-height: 1.6;
        }

        /* Header */
        header {
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo a {
            text-decoration: none;
            color: var(--primary-color);
            font-weight: bold;
            font-size: 24px;
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
            padding: 10px 40px 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
        }

        .search-bar button {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: var(--primary-color);
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
        }

        /* Navigation */
        .category-nav {
            background-color: white;
            border-bottom: 1px solid var(--border-color);
            overflow-x: auto;
            white-space: nowrap;
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
            margin-right: 20px;
        }

        .category-nav a {
            display: inline-block;
            text-decoration: none;
            color: var(--text-color);
            padding: 12px 0;
            font-size: 14px;
            position: relative;
        }

        /* Main content */
        main {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .page-title {
            font-size: 24px;
            margin-bottom: 30px;
            color: var(--primary-color);
            text-align: center;
        }

        /* Form container */
        .form-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 40px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-color);
        }

        .required-star {
            color: var(--error-color);
            margin-left: 3px;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(255, 140, 0, 0.1);
            outline: none;
        }

        .form-control.error {
            border-color: var(--error-color);
        }

        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }

        .form-hint {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: #777;
        }

        .form-error {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: var(--error-color);
        }

        .image-preview {
            width: 100%;
            max-width: 300px;
            margin-top: 10px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
        }

        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }

        .alert-success {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .alert-error {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--error-color);
        }

        .form-actions {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 30px;
        }

        .submit-button {
            flex: 1;
            padding: 12px 24px;
            background-color: var(--primary-color);
            color: white;
            font-weight: bold;
            font-size: 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .submit-button:hover {
            background-color: var(--secondary-color);
        }

        .cancel-button {
            padding: 12px 24px;
            background-color: white;
            color: #777;
            font-weight: bold;
            font-size: 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: background-color 0.3s;
        }

        .cancel-button:hover {
            background-color: #f8f8f8;
        }

        /* Footer */
        footer {
            background-color: var(--light-background);
            border-top: 1px solid var(--border-color);
            padding: 40px 0;
            margin-top: 40px;
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
            margin-bottom: 15px;
            color: var(--primary-color);
        }

        .footer-column ul {
            list-style: none;
        }

        .footer-column li {
            margin-bottom: 8px;
        }

        .footer-column a {
            text-decoration: none;
            color: #777;
            font-size: 14px;
        }

        .copyright {
            max-width: 1200px;
            margin: 20px auto 0;
            padding: 20px 20px 0;
            border-top: 1px solid var(--border-color);
            text-align: center;
            color: #777;
            font-size: 14px;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Fonction pour prévisualiser l'image
            const imageUrlInput = document.getElementById('image_url');
            const imagePreview = document.getElementById('image-preview');
            const fileInput = document.getElementById('image');

            if (imageUrlInput && imagePreview) {
                // Prévisualiser l'image à partir de l'URL
                imageUrlInput.addEventListener('blur', function() {
                    if (this.value) {
                        imagePreview.src = this.value;
                        imagePreview.style.display = 'block';
                    } else {
                        imagePreview.style.display = 'none';
                    }
                });

                // Initialiser la prévisualisation
                if (imageUrlInput.value) {
                    imagePreview.src = imageUrlInput.value;
                    imagePreview.style.display = 'block';
                }
            }

            // Prévisualiser l'image depuis l'upload de fichier
            if (fileInput && imagePreview) {
                fileInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();

                        reader.onload = function(e) {
                            imagePreview.src = e.target.result;
                            imagePreview.style.display = 'block';
                        };

                        reader.readAsDataURL(this.files[0]);
                    }
                });
            }
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
                <button type="submit">🔍</button>
            </form>
        </div>

        <div class="header-actions">
            <a href="compte.php"><?= htmlspecialchars($_SESSION['user_nom'] ?? 'Mon compte'); ?></a>
            <a href="messages.php">Messages</a>
            <a href="mes_articles.php"><strong>Mes Articles</strong></a>
            <a href="logout.php">Déconnexion</a>
        </div>
    </div>

    <nav class="category-nav">
        <ul>
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
    <h1 class="page-title">Modifier mon article</h1>

    <div class="form-container">
        <?php if ($success): ?>
            <div class="alert alert-success">
                <strong>Succès!</strong> Les informations de votre article ont été mises à jour avec succès.
                <p>Vous allez être redirigé vers vos articles...</p>
            </div>
        <?php endif; ?>

        <?php if (isset($errors['db'])): ?>
            <div class="alert alert-error">
                <strong>Erreur!</strong> <?= $errors['db'] ?>
            </div>
        <?php endif; ?>

        <form method="post" action="modifier_produit.php?id=<?= $product_id ?>" enctype="multipart/form-data">
            <div class="form-group">
                <label for="titre">Titre de l'article<span class="required-star">*</span></label>
                <input type="text" class="form-control <?= isset($errors['titre']) ? 'error' : '' ?>" id="titre" name="titre" value="<?= htmlspecialchars($produit['titre']) ?>" required>
                <?php if (isset($errors['titre'])): ?>
                    <span class="form-error"><?= $errors['titre'] ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="description">Description<span class="required-star">*</span></label>
                <textarea class="form-control <?= isset($errors['description']) ? 'error' : '' ?>" id="description" name="description" rows="5" required><?= htmlspecialchars($produit['description']) ?></textarea>
                <?php if (isset($errors['description'])): ?>
                    <span class="form-error"><?= $errors['description'] ?></span>
                <?php endif; ?>
                <span class="form-hint">Décrivez votre article en détail (état, taille, matière, etc.)</span>
            </div>

            <div class="form-group">
                <label for="prix">Prix (€)<span class="required-star">*</span></label>
                <input type="number" step="0.01" min="0.01" class="form-control <?= isset($errors['prix']) ? 'error' : '' ?>" id="prix" name="prix" value="<?= htmlspecialchars($produit['prix']) ?>" required>
                <?php if (isset($errors['prix'])): ?>
                    <span class="form-error"><?= $errors['prix'] ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="categorie_id">Catégorie<span class="required-star">*</span></label>
                <select class="form-control <?= isset($errors['categorie_id']) ? 'error' : '' ?>" id="categorie_id" name="categorie_id" required>
                    <option value="">Sélectionner une catégorie</option>
                    <?php foreach ($categories_filtrees as $category): ?>
                        <option value="<?= $category['id'] ?>" <?= $produit['categorie_id'] == $category['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['categorie_id'])): ?>
                    <span class="form-error"><?= $errors['categorie_id'] ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="image_url">URL de l'image<span class="required-star">*</span></label>
                <input type="text" class="form-control <?= isset($errors['image_url']) ? 'error' : '' ?>" id="image_url" name="image_url" value="<?= htmlspecialchars($produit['image_url']) ?>" required>
                <?php if (isset($errors['image_url'])): ?>
                    <span class="form-error"><?= $errors['image_url'] ?></span>
                <?php endif; ?>
                <span class="form-hint">Entrez l'URL de l'image de votre article</span>
                <img id="image-preview" class="image-preview" src="<?= htmlspecialchars($produit['image_url']) ?>" alt="Prévisualisation" style="display: <?= empty($produit['image_url']) ? 'none' : 'block' ?>;">
            </div>

            <div class="form-group">
                <label for="image">Ou uploadez une nouvelle image</label>
                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                <?php if (isset($errors['image'])): ?>
                    <span class="form-error"><?= $errors['image'] ?></span>
                <?php endif; ?>
                <span class="form-hint">Formats acceptés: JPG, JPEG, PNG, GIF</span>
            </div>

            <div class="form-actions">
                <a href="mes_articles.php" class="cancel-button">Annuler</a>
                <button type="submit" class="submit-button">Enregistrer les modifications</button>
            </div>
        </form>
    </div>
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
        <p>&copy; 2025 BANDER-SHOP - Tous droits réservés.</p>
    </div>
</footer>

</body>
</html>
