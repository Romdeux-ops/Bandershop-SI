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

// Vérifiez si l'utilisateur existe
if (!$user) {
    echo "Utilisateur non trouvé.";
    exit();
}

// Initialiser les variables d'erreur
$errors = [];
$success = false;

// Traiter le formulaire lorsqu'il est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer et valider les données du formulaire
    $prenom = trim($_POST['prenom'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $age = trim($_POST['age'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    
    // Validation du prénom
    if (empty($prenom)) {
        $errors['prenom'] = "Le prénom est requis";
    } elseif (strlen($prenom) > 100) {
        $errors['prenom'] = "Le prénom ne doit pas dépasser 100 caractères";
    }
    
    // Validation du nom
    if (empty($nom)) {
        $errors['nom'] = "Le nom est requis";
    } elseif (strlen($nom) > 100) {
        $errors['nom'] = "Le nom ne doit pas dépasser 100 caractères";
    }
    
    // Validation de l'email
    if (empty($email)) {
        $errors['email'] = "L'email est requis";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "L'email n'est pas valide";
    } elseif (strlen($email) > 150) {
        $errors['email'] = "L'email ne doit pas dépasser 150 caractères";
    } else {
        // Vérifier si l'email existe déjà (sauf pour l'utilisateur actuel)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $errors['email'] = "Cet email est déjà utilisé par un autre compte";
        }
    }
    
    // Validation de l'âge
    if (!empty($age)) {
        if (!is_numeric($age) || $age < 0 || $age > 120) {
            $errors['age'] = "L'âge doit être un nombre compris entre 0 et 120";
        }
    }
    
    // Validation du téléphone (format international)
    if (!empty($telephone)) {
        if (!preg_match("/^\+?[0-9]{10,15}$/", $telephone)) {
            $errors['telephone'] = "Le numéro de téléphone n'est pas valide (10 à 15 chiffres, peut commencer par +)";
        }
    }
    
    // Si aucune erreur, mettre à jour le profil
    if (empty($errors)) {
        try {
            // Préparer la requête de mise à jour
            $sql = "UPDATE users SET prenom = ?, nom = ?, email = ?, age = ?, adresse = ?, telephone = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$prenom, $nom, $email, $age ?: null, $adresse, $telephone, $_SESSION['user_id']]);
            
            // Mettre à jour la session avec le nouveau nom
            $_SESSION['user_nom'] = $nom;
            
            // Marquer comme succès
            $success = true;
            
            // Récupérer les nouvelles informations pour affichage
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            $errors['db'] = "Erreur lors de la mise à jour du profil: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier mon profil - BANDER-SHOP</title>
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

        .sell-button {
            background-color: var(--primary-color);
            color: white !important;
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: bold;
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

        .category-nav a.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: var(--primary-color);
        }

        /* Main content */
        main {
            max-width: 1200px;
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
            max-width: 700px;
            margin: 0 auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }

        .form-header {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
            text-align: center;
        }

        .form-title {
            font-size: 20px;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .form-subtitle {
            font-size: 14px;
            color: #777;
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
            justify-content: space-between;
            margin-top: 30px;
        }

        .submit-button {
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

        .cancel-link {
            text-decoration: none;
            color: #777;
            font-size: 14px;
            padding: 12px 24px;
            border: 1px solid #ddd;
            border-radius: 4px;
            transition: background-color 0.3s;
        }

        .cancel-link:hover {
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
            // Fonction de validation personnalisée pour chaque champ
            const validateField = (field, errorElement, validationFunc) => {
                const value = field.value.trim();
                const errorMessage = validationFunc(value);
                
                if (errorMessage) {
                    field.classList.add('error');
                    errorElement.textContent = errorMessage;
                    return false;
                } else {
                    field.classList.remove('error');
                    errorElement.textContent = '';
                    return true;
                }
            };
            
            // Ajouter des écouteurs d'événements pour chaque champ
            document.querySelectorAll('input, textarea').forEach(field => {
                const errorId = field.id + '-error';
                const errorElement = document.getElementById(errorId);
                
                if (errorElement) {
                    field.addEventListener('blur', () => {
                        switch(field.id) {
                            case 'prenom':
                                validateField(field, errorElement, value => {
                                    if (!value) return 'Le prénom est requis';
                                    if (value.length > 100) return 'Le prénom ne doit pas dépasser 100 caractères';
                                    return '';
                                });
                                break;
                            case 'nom':
                                validateField(field, errorElement, value => {
                                    if (!value) return 'Le nom est requis';
                                    if (value.length > 100) return 'Le nom ne doit pas dépasser 100 caractères';
                                    return '';
                                });
                                break;
                            case 'email':
                                validateField(field, errorElement, value => {
                                    if (!value) return 'L\'email est requis';
                                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return 'L\'email n\'est pas valide';
                                    if (value.length > 150) return 'L\'email ne doit pas dépasser 150 caractères';
                                    return '';
                                });
                                break;
                            case 'age':
                                validateField(field, errorElement, value => {
                                    if (value && (isNaN(value) || value < 0 || value > 120)) {
                                        return 'L\'âge doit être un nombre compris entre 0 et 120';
                                    }
                                    return '';
                                });
                                break;
                            case 'telephone':
                                validateField(field, errorElement, value => {
                                    if (value && !/^\+?[0-9]{10,15}$/.test(value)) {
                                        return 'Le numéro de téléphone n\'est pas valide (10 à 15 chiffres, peut commencer par +)';
                                    }
                                    return '';
                                });
                                break;
                        }
                    });
                }
            });
            
            // Validation du formulaire à la soumission
            document.getElementById('profile-form').addEventListener('submit', function(event) {
                let formValid = true;
                
                // Valider chaque champ requis
                const fields = [
                    { id: 'prenom', errorId: 'prenom-error', validationFunc: value => !value ? 'Le prénom est requis' : '' },
                    { id: 'nom', errorId: 'nom-error', validationFunc: value => !value ? 'Le nom est requis' : '' },
                    { id: 'email', errorId: 'email-error', validationFunc: value => !value ? 'L\'email est requis' : '' }
                ];
                
                fields.forEach(field => {
                    const fieldElement = document.getElementById(field.id);
                    const errorElement = document.getElementById(field.errorId);
                    
                    if (!validateField(fieldElement, errorElement, field.validationFunc)) {
                        formValid = false;
                    }
                });
                
                if (!formValid) {
                    event.preventDefault();
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
                <button type="submit">🔍</button>
            </form>
        </div>

        <div class="header-actions">
            <a href="compte.php"><strong><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></strong></a>
            <a href="messages.php">Messages</a>
            <a href="mes_articles.php">Mes Articles</a>
            <a href="suivi_commande.php">Mes Commandes</a>
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
    <h1 class="page-title">Modifier mon profil</h1>

    <div class="form-container">
        <div class="form-header">
            <h2 class="form-title">Informations personnelles</h2>
            <p class="form-subtitle">Modifiez vos informations personnelles ci-dessous</p>
        </div>
        
        <?php if ($success): ?>
        <div class="alert alert-success">
            <strong>Succès!</strong> Vos informations ont été mises à jour avec succès.
        </div>
        <?php endif; ?>
        
        <?php if (isset($errors['db'])): ?>
        <div class="alert alert-error">
            <strong>Erreur!</strong> <?= $errors['db'] ?>
        </div>
        <?php endif; ?>

        <form id="profile-form" method="post" action="modifier_profil.php">
            <div class="form-group">
                <label for="prenom">Prénom<span class="required-star">*</span></label>
                <input type="text" class="form-control <?= isset($errors['prenom']) ? 'error' : '' ?>" id="prenom" name="prenom" value="<?= htmlspecialchars($user['prenom']) ?>" required>
                <span id="prenom-error" class="form-error"><?= $errors['prenom'] ?? '' ?></span>
            </div>

            <div class="form-group">
                <label for="nom">Nom<span class="required-star">*</span></label>
                <input type="text" class="form-control <?= isset($errors['nom']) ? 'error' : '' ?>" id="nom" name="nom" value="<?= htmlspecialchars($user['nom']) ?>" required>
                <span id="nom-error" class="form-error"><?= $errors['nom'] ?? '' ?></span>
            </div>

            <div class="form-group">
                <label for="email">Email<span class="required-star">*</span></label>
                <input type="email" class="form-control <?= isset($errors['email']) ? 'error' : '' ?>" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                <span id="email-error" class="form-error"><?= $errors['email'] ?? '' ?></span>
                <span class="form-hint">Nous ne partagerons jamais votre email avec qui que ce soit.</span>
            </div>

            <div class="form-group">
                <label for="age">Âge</label>
                <input type="number" class="form-control <?= isset($errors['age']) ? 'error' : '' ?>" id="age" name="age" value="<?= htmlspecialchars($user['age'] ?? '') ?>" min="0" max="120">
                <span id="age-error" class="form-error"><?= $errors['age'] ?? '' ?></span>
            </div>

            <div class="form-group">
                <label for="adresse">Adresse</label>
                <textarea class="form-control <?= isset($errors['adresse']) ? 'error' : '' ?>" id="adresse" name="adresse" rows="3"><?= htmlspecialchars($user['adresse'] ?? '') ?></textarea>
                <span id="adresse-error" class="form-error"><?= $errors['adresse'] ?? '' ?></span>
                <span class="form-hint">Utilisée pour la livraison de vos commandes</span>
            </div>

            <div class="form-group">
                <label for="telephone">Téléphone</label>
                <input type="tel" class="form-control <?= isset($errors['telephone']) ? 'error' : '' ?>" id="telephone" name="telephone" value="<?= htmlspecialchars($user['telephone'] ?? '') ?>" placeholder="+33612345678">
                <span id="telephone-error" class="form-error"><?= $errors['telephone'] ?? '' ?></span>
                <span class="form-hint">Format international recommandé: +33612345678</span>
            </div>

            <div class="form-actions">
                <a href="compte.php" class="cancel-link">Annuler</a>
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