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

// Vérifiez si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$receiver_id = isset($_GET['receiver_id']) ? intval($_GET['receiver_id']) : null;

// Récupérer les conversations de l'utilisateur
$stmt = $pdo->prepare("
    SELECT DISTINCT m.receiver_id, u.nom AS receiver_name
    FROM messages m
    JOIN users u ON m.receiver_id = u.id
    WHERE m.sender_id = ?
    UNION
    SELECT DISTINCT m.sender_id, u.nom AS receiver_name
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.receiver_id = ?
");
$stmt->execute([$user_id, $user_id]);
$conversations = $stmt->fetchAll();

// Récupérer les messages entre l'utilisateur et le destinataire sélectionné
if ($receiver_id) {
    $stmt = $pdo->prepare("
        SELECT m.*, u.nom AS sender_name
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.date_envoi ASC
    ");
    $stmt->execute([$user_id, $receiver_id, $receiver_id, $user_id]);
    $messages = $stmt->fetchAll();

    // Récupérer le nom du destinataire pour l'affichage
    $stmt = $pdo->prepare("SELECT nom FROM users WHERE id = ?");
    $stmt->execute([$receiver_id]);
    $receiver = $stmt->fetch();
    $receiver_name = $receiver ? $receiver['nom'] : 'Utilisateur';
}

// Traitement du formulaire de message
if (isset($_POST['send_message']) && !empty($_POST['message'])) {
    $message = trim($_POST['message']);
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, date_envoi) VALUES (?, ?, ?, NOW())");
    if ($stmt->execute([$user_id, $receiver_id, $message])) {
        header("Location: messages.php?receiver_id=$receiver_id");
        exit();
    } else {
        $error_message = "Erreur lors de l'envoi du message.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messagerie - BANDER-SHOP</title>
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
            --message-sent: #FFF0E0; /* Fond pour messages envoyés */
            --message-received: #F0F2F5; /* Fond pour messages reçus */
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

        /* Messaging layout */
        .messaging-container {
            display: flex;
            background-color: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            min-height: 600px;
        }

        @media (max-width: 992px) {
            .messaging-container {
                flex-direction: column;
                min-height: 800px;
            }
        }

        /* Conversations sidebar */
        .conversations-sidebar {
            width: 320px;
            border-right: 1px solid var(--border-color);
            background-color: white;
            display: flex;
            flex-direction: column;
        }

        @media (max-width: 992px) {
            .conversations-sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid var(--border-color);
                max-height: 300px;
            }
        }

        .conversations-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            color: var(--secondary-color);
            font-weight: 700;
            font-size: 18px;
            display: flex;
            align-items: center;
        }

        .conversations-header i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        .conversations-list {
            list-style: none;
            overflow-y: auto;
            flex: 1;
        }

        .conversation-item {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            transition: all var(--transition-speed);
            cursor: pointer;
        }

        .conversation-item:hover {
            background-color: var(--light-gray);
        }

        .conversation-item.active {
            background-color: var(--light-gray);
            border-left: 4px solid var(--primary-color);
        }

        .conversation-item a {
            text-decoration: none;
            color: var(--secondary-color);
            display: flex;
            align-items: center;
            font-weight: 500;
        }

        .conversation-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--light-gray);
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-right: 15px;
            font-size: 16px;
        }

        /* Messages area */
        .messages-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background-color: white;
        }

        .messages-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            font-weight: 700;
            color: var(--secondary-color);
            font-size: 18px;
            display: flex;
            align-items: center;
        }

        .messages-header i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        .messages-list {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background-color: var(--light-gray);
            display: flex;
            flex-direction: column;
        }

        .empty-state {
            text-align: center;
            padding: 60px 0;
            color: #777;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
        }

        .empty-state i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state p {
            font-size: 16px;
            margin-bottom: 20px;
        }

        .message-item {
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            max-width: 80%;
        }

        .message-content {
            padding: 15px;
            border-radius: 18px;
            position: relative;
            word-wrap: break-word;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            line-height: 1.5;
        }

        .message-sent {
            align-self: flex-end;
            background-color: var(--message-sent);
            border-bottom-right-radius: 4px;
        }

        .message-received {
            align-self: flex-start;
            background-color: var(--message-received);
            border-bottom-left-radius: 4px;
        }

        .message-info {
            font-size: 12px;
            margin-top: 5px;
            color: #777;
        }

        .message-sender {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--secondary-color);
        }

        .message-time {
            font-size: 11px;
            color: #777;
            margin-top: 5px;
            align-self: flex-end;
        }

        .message-sent .message-time {
            text-align: right;
        }

        /* Message form */
        .message-form {
            padding: 15px 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            background-color: white;
            gap: 10px;
        }

        .message-input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 24px;
            resize: none;
            font-size: 15px;
            max-height: 120px;
            background-color: var(--light-gray);
            transition: all var(--transition-speed);
        }

        .message-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 140, 0, 0.1);
        }

        .send-button {
            padding: 12px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition-speed);
            box-shadow: 0 2px 5px rgba(255, 140, 0, 0.2);
        }

        .send-button:hover {
            background-color: #E07B00;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(255, 140, 0, 0.25);
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
            
            .message-item {
                max-width: 90%;
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
            
            .message-item {
                max-width: 95%;
            }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Scroll to bottom of messages list
            const messagesList = document.querySelector('.messages-list');
            if (messagesList) {
                messagesList.scrollTop = messagesList.scrollHeight;
            }
            
            // Auto-resize textarea
            const messageInput = document.querySelector('.message-input');
            if (messageInput) {
                messageInput.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
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

        <div class="header-actions">
            <a href="compte.php"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['user_nom']); ?></a>
            <a href="messages.php"><i class="fas fa-envelope"></i> <strong>Messages</strong></a>
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
    <h1 class="page-title">Messagerie</h1>

    <div class="messaging-container">
        <div class="conversations-sidebar">
            <div class="conversations-header">
                <i class="fas fa-comments"></i> Conversations
            </div>
            <ul class="conversations-list">
                <?php if (empty($conversations)): ?>
                    <li class="conversation-item">
                        <span style="padding: 15px 20px; display: block; color: #777; text-align: center;">Aucune conversation</span>
                    </li>
                <?php else: ?>
                    <?php foreach ($conversations as $conversation): ?>
                        <li class="conversation-item <?= ($receiver_id == $conversation['receiver_id']) ? 'active' : '' ?>">
                            <a href="messages.php?receiver_id=<?= $conversation['receiver_id'] ?>">
                                <div class="conversation-avatar">
                                    <?= strtoupper(substr($conversation['receiver_name'], 0, 1)) ?>
                                </div>
                                <?= htmlspecialchars($conversation['receiver_name']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>

        <div class="messages-area">
            <?php if ($receiver_id): ?>
                <div class="messages-header">
                    <i class="fas fa-user"></i> Discussion avec <?= htmlspecialchars($receiver_name) ?>
                </div>
                <div class="messages-list">
                    <?php if (empty($messages)): ?>
                        <div class="empty-state">
                            <i class="fas fa-comments"></i>
                            <p>Aucun message. Commencez la conversation!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                            <div class="message-item">
                                <div class="message-content <?= ($message['sender_id'] == $user_id) ? 'message-sent' : 'message-received' ?>">
                                    <?php if ($message['sender_id'] != $user_id): ?>
                                        <div class="message-sender"><?= htmlspecialchars($message['sender_name']) ?></div>
                                    <?php endif; ?>
                                    <div><?= nl2br(htmlspecialchars($message['message'])) ?></div>
                                </div>
                                <div class="message-time <?= ($message['sender_id'] == $user_id) ? 'sent' : 'received' ?>">
                                    <?= (new DateTime($message['date_envoi']))->format('d/m/Y H:i') ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <form class="message-form" method="post" action="messages.php?receiver_id=<?= $receiver_id ?>">
                    <textarea class="message-input" name="message" placeholder="Écrivez votre message..." required></textarea>
                    <button type="submit" name="send_message" class="send-button">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-comments"></i>
                    <p>Sélectionnez une conversation ou commencez-en une nouvelle.</p>
                </div>
            <?php endif; ?>
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