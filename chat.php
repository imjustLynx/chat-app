<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get all users except current user
$stmt = $pdo->prepare("SELECT id, username FROM users WHERE id != ?");
$stmt->execute([$_SESSION['user_id']]);
$users = $stmt->fetchAll();

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message']) && isset($_POST['receiver_id'])) {
    $message = $_POST['message'];
    $receiver_id = $_POST['receiver_id'];
    
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $receiver_id, $message]);
    
    // Return the new message as JSON
    $newMessage = [
        'message' => $message,
        'sender_id' => $_SESSION['user_id'],
        'created_at' => date('Y-m-d H:i:s')
    ];
    echo json_encode($newMessage);
    exit();
}

// Handle clear chat
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['clear_chat']) && isset($_POST['receiver_id'])) {
    $receiver_id = $_POST['receiver_id'];
    $stmt = $pdo->prepare("DELETE FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
    $stmt->execute([$_SESSION['user_id'], $receiver_id, $receiver_id, $_SESSION['user_id']]);
    echo json_encode(['status' => 'success']);
    exit();
}

// Get selected user's messages
$selected_user_id = isset($_GET['user']) ? $_GET['user'] : null;
$messages = [];
if ($selected_user_id) {
    $stmt = $pdo->prepare("
        SELECT m.*, u.username as sender_name 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE (sender_id = ? AND receiver_id = ?) 
        OR (sender_id = ? AND receiver_id = ?)
        ORDER BY created_at ASC
    ");
    $stmt->execute([$_SESSION['user_id'], $selected_user_id, $selected_user_id, $_SESSION['user_id']]);
    $messages = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LetsTalk - WT Lab Project</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="chat-container">
        <div class="sidebar">
            <div class="user-info">
                <h3>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h3>
                <a href="logout.php">Logout</a>
            </div>
            <div class="users-list">
                <h4>Users</h4>
                <?php foreach ($users as $user): ?>
                    <a href="?user=<?php echo $user['id']; ?>" class="user-item <?php echo $selected_user_id == $user['id'] ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($user['username']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="chat-area">
            <?php if ($selected_user_id): ?>
                <div class="chat-header">
                    <h3>Chat with <?php 
                        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                        $stmt->execute([$selected_user_id]);
                        $chat_user = $stmt->fetch();
                        echo htmlspecialchars($chat_user['username']);
                    ?></h3>
                    <button id="clearChat" class="clear-chat-btn">Clear Chat</button>
                </div>
                <div class="messages" id="messages">
                    <?php foreach ($messages as $message): ?>
                        <div class="message <?php echo $message['sender_id'] == $_SESSION['user_id'] ? 'sent' : 'received'; ?>">
                            <div class="message-content">
                                <?php echo htmlspecialchars($message['message']); ?>
                            </div>
                            <div class="message-time">
                                <?php echo date('H:i', strtotime($message['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <form class="message-form" id="messageForm" method="POST">
                    <input type="hidden" name="receiver_id" value="<?php echo $selected_user_id; ?>">
                    <input type="text" name="message" id="messageInput" placeholder="Type your message..." required>
                    <button type="submit">Send</button>
                </form>
            <?php else: ?>
                <div class="select-user">
                    <p>Select a user to start chatting</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Auto-refresh messages every 2 seconds
            setInterval(function() {
                if ($('#messages').length) {
                    $.get(window.location.href, function(data) {
                        var newMessages = $(data).find('#messages').html();
                        $('#messages').html(newMessages);
                        scrollToBottom();
                    });
                }
            }, 2000);

            // Handle message sending
            $('#messageForm').on('submit', function(e) {
                e.preventDefault();
                var message = $('#messageInput').val();
                var receiver_id = $('input[name="receiver_id"]').val();
                
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: {
                        message: message,
                        receiver_id: receiver_id
                    },
                    success: function(response) {
                        $('#messageInput').val('');
                        scrollToBottom();
                    }
                });
            });

            // Handle clear chat
            $('#clearChat').on('click', function() {
                if (confirm('Are you sure you want to clear this chat?')) {
                    var receiver_id = $('input[name="receiver_id"]').val();
                    
                    $.ajax({
                        url: window.location.href,
                        type: 'POST',
                        data: {
                            clear_chat: true,
                            receiver_id: receiver_id
                        },
                        success: function() {
                            $('#messages').empty();
                        }
                    });
                }
            });

            // Scroll to bottom of messages
            function scrollToBottom() {
                var messages = document.getElementById('messages');
                messages.scrollTop = messages.scrollHeight;
            }
            scrollToBottom();
        });
    </script>
</body>
</html> 