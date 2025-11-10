<?php
session_start();

// Connexion à la base de données
try {
    $pdo = new PDO("mysql:host=localhost;dbname=e_boutique", "root", "rootroot");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_produit'])) {
    $id_produit = intval($_POST['id_produit']);
    $user_id = $_SESSION['user_id'];

    // Vérifier que l'utilisateur est le propriétaire du produit
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND vendeur_id = ?");
    $stmt->execute([$id_produit, $user_id]);
    $produit = $stmt->fetch();

    if ($produit) {
        // Supprimer le produit
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id_produit]);

        // Supprimer l'image associée
        $image_path = "images/produits/" . $produit['id'] . "/image.jpg";
        if (file_exists($image_path)) {
            unlink($image_path);
        }

        header("Location: mes_articles.php");
        exit();
    } else {
        echo "Vous n'avez pas la permission de supprimer cet article.";
    }
} else {
    echo "Produit non trouvé.";
}
?>
