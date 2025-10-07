<?php
session_start();
// NOTE: Assuming db_connection.php and functions.php handle session initialization and database connection.
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
requireLogin();

$user_id = $_SESSION['user_id'];
$message = [];

// Get the user ID of the conversation partner to view, if selected
$selected_thread_id = isset($_GET['thread_id']) ? intval($_GET['thread_id']) : 0;

// --- Helper Functions (For Data Retrieval) ---

/**
 * Fetches the user's conversation list, showing the last message and unread count for each partner.
 * @param mysqli $conn The database connection.
 * @param int $user_id The current user's ID.
 * @return array The list of conversations.
 */
function getConversations(mysqli $conn, int $user_id): array {
    // This query finds the latest message for each unique partner the user has communicated with.
    // It requires the current user ID to be bound 5 times: 3 for partner identification, 1 for unread count receiver, 1 for unread count sender check.
    $query = "
        SELECT
            t1.message_id,
            t1.message_text,
            t1.created_at,
            t2.thread_user_id,
            u.full_name,
            u.profile_photo,
            (
                SELECT COUNT(*) FROM messages m2 
                WHERE m2.receiver_id = ? AND m2.is_read = FALSE AND m2.sender_id = t2.thread_user_id
            ) AS unread_count
        FROM (
            -- Get the most recent message ID for each partner (t2)
            SELECT
                MAX(message_id) AS latest_message_id,
                IF(sender_id = ?, receiver_id, sender_id) AS thread_user_id
            FROM messages
            WHERE sender_id = ? OR receiver_id = ?
            GROUP BY thread_user_id
        ) AS t2
        -- Join back to messages table to get the message details (t1)
        JOIN messages t1 ON t2.latest_message_id = t1.message_id
        -- Join to users table to get the partner's name and photo (u)
        JOIN users u ON t2.thread_user_id = u.user_id
        ORDER BY t1.created_at DESC;
    ";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("SQL Prepare Error (Conversations): " . $conn->error);
        return [];
    }

    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);

    if (!$stmt->execute()) {
        error_log("SQL Execute Error (Conversations): " . $stmt->error);
        $stmt->close();
        return [];
    }
    
    $result = $stmt->get_result();
    $conversations = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $conversations;
}

// Fetch all conversations
$conversations = getConversations($conn, $user_id);

// If a thread is selected, we need to know who the partner is for display
$selected_partner_name = "Select a Conversation";
$selected_partner_photo = "https://placehold.co/50x50/3498db/FFFFFF?text=P"; // Default partner photo

if ($selected_thread_id) {
    // Fetch the selected partner's details
    $partner_stmt = $conn->prepare("SELECT full_name, profile_photo FROM users WHERE user_id = ?");
    $partner_stmt->bind_param("i", $selected_thread_id);
    $partner_stmt->execute();
    $partner_result = $partner_stmt->get_result()->fetch_assoc();

    if ($partner_result) {
        $selected_partner_name = htmlspecialchars($partner_result['full_name']);
        if (!empty($partner_result['profile_photo'])) {
            $selected_partner_photo = '../assets/images/profiles/' . htmlspecialchars($partner_result['profile_photo']);
        }
    }
    $partner_stmt->close();

    // Mark all messages from the selected partner as read (optimistic approach)
    $mark_read_stmt = $conn->prepare("UPDATE messages SET is_read = TRUE WHERE sender_id = ? AND receiver_id = ? AND is_read = FALSE");
    $mark_read_stmt->bind_param("ii", $selected_thread_id, $user_id);
    $mark_read_stmt->execute();
    $mark_read_stmt->close();
}


// Function to safely get profile photo URL
function getProfilePhotoUrl(?string $photo): string {
    if (!empty($photo)) {
        return '../assets/images/profiles/' . htmlspecialchars($photo);
    }
    return 'https://placehold.co/50x50/3498db/FFFFFF?text=U';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Messages - Rentflow360</title>
    <!-- Assuming external styles are linked -->
    <link rel="stylesheet" href="../assets/css/style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Embedded styles for Chat UI -->
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --text-color: #343a40;
            --bg-light: #f8f9fa;
            --card-bg: #ffffff;
            --border-color: #dee2e6;
            --shadow-light: 0 4px 12px rgba(0, 0, 0, 0.05);
            --chat-bubble-user: #007bff;
            --chat-bubble-partner: #e9ecef;
            --font-family: 'Inter', sans-serif;
        }

        /* Structural & Layout */
        body { font-family: var(--font-family); color: var(--text-color); background-color: var(--bg-light); }
        .dashboard-wrapper { display: flex; min-height: 100vh; }
        .dashboard-content { width: 100%; padding: 0; flex-grow: 1; display: flex; }
        
        /* Message Area */
        .message-app-container { display: flex; width: 100%; max-width: 1200px; margin: 2rem auto; border-radius: 1rem; box-shadow: var(--shadow-light); overflow: hidden; height: calc(100vh - 4rem); background-color: var(--card-bg); }
        
        /* Conversation List (Left Panel) */
        .conversation-list { width: 350px; border-right: 1px solid var(--border-color); background-color: #f4f7f6; overflow-y: auto; padding: 0; }
        .conversation-list h2 { font-size: 1.25rem; padding: 1rem; margin: 0; border-bottom: 1px solid var(--border-color); color: var(--text-color); }

        .conversation-item { 
            display: flex; align-items: center; padding: 1rem; border-bottom: 1px solid var(--border-color); 
            cursor: pointer; transition: background-color 0.2s, box-shadow 0.2s; text-decoration: none; color: inherit;
        }
        .conversation-item:hover { background-color: var(--bg-light); }
        .conversation-item.active { background-color: #e0f0ff; border-left: 4px solid var(--primary-color); }

        .profile-pic { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; margin-right: 1rem; border: 2px solid var(--border-color); }
        .conv-details { flex-grow: 1; overflow: hidden; }
        .conv-name { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 0.2rem; }
        .conv-last-msg { font-size: 0.85rem; color: var(--secondary-color); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .conv-time-unread { text-align: right; font-size: 0.75rem; color: var(--secondary-color); }
        .unread-badge { background-color: var(--danger-color); color: white; border-radius: 50%; padding: 0.3rem 0.5rem; font-size: 0.7rem; margin-left: 0.5rem; }

        /* Chat Window (Right Panel) */
        .chat-window { flex-grow: 1; display: flex; flex-direction: column; }
        .chat-header { display: flex; align-items: center; padding: 1rem; border-bottom: 1px solid var(--border-color); background-color: var(--card-bg); }
        .chat-header h3 { margin: 0; font-size: 1.2rem; }

        .chat-messages { flex-grow: 1; padding: 1rem; overflow-y: auto; display: flex; flex-direction: column; gap: 0.5rem; background-color: var(--bg-light); }
        .empty-chat { text-align: center; color: var(--secondary-color); margin-top: 30%; }

        /* Message Bubbles */
        .message-bubble { max-width: 80%; padding: 0.75rem 1rem; border-radius: 1rem; line-height: 1.4; position: relative; }
        .message-bubble p { margin: 0; }
        .msg-time { display: block; font-size: 0.65rem; color: #aaa; margin-top: 5px; text-align: right; }
        
        .sent { background-color: var(--chat-bubble-user); color: white; align-self: flex-end; border-bottom-right-radius: 0.2rem; }
        .received { background-color: var(--chat-bubble-partner); color: var(--text-color); align-self: flex-start; border-bottom-left-radius: 0.2rem; }
        
        /* Message Input */
        .chat-input { padding: 1rem; border-top: 1px solid var(--border-color); background-color: var(--card-bg); }
        .chat-input form { display: flex; }
        .chat-input textarea { flex-grow: 1; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 0.5rem; resize: none; margin-right: 0.5rem; font-size: 1rem; height: 40px; }
        .chat-input button { width: 100px; padding: 0.75rem; border: none; background-color: var(--primary-color); color: white; border-radius: 0.5rem; cursor: pointer; transition: background-color 0.2s; }
        .chat-input button:hover { background-color: #0056b3; }

        /* Error/Loading Message */
        #chatMessageArea { text-align: center; padding: 1rem; font-weight: 600; color: var(--danger-color); }
        .loader { border: 4px solid #f3f3f3; border-top: 4px solid var(--primary-color); border-radius: 50%; width: 20px; height: 20px; animation: spin 2s linear infinite; display: inline-block; margin-right: 8px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        /* Responsive Design */
        @media (max-width: 900px) {
            .message-app-container { margin: 0; height: 100vh; border-radius: 0; }
            .conversation-list { width: 100%; position: absolute; z-index: 10; height: 100%; transition: transform 0.3s ease-in-out; }
            .conversation-list.hidden-on-mobile { transform: translateX(-100%); }
            .chat-window { width: 100%; }
        }
        
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include '../includes/sidebar.php'; // Included sidebar ?>
        
        <div class="dashboard-content">
            <div class="message-app-container">

                <!-- 1. Conversation List Panel -->
                <div class="conversation-list" id="conversationList">
                    <h2>Conversations</h2>
                    <?php if (empty($conversations)): ?>
                        <div style="padding: 1rem; text-align: center; color: var(--secondary-color);">
                            <i class="fas fa-inbox fa-2x" style="margin-bottom: 0.5rem;"></i>
                            <p>No conversations started yet. Contact an agent on a listing to begin!</p>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($conversations as $conv):
                        $partner_id = $conv['thread_user_id'];
                        $is_active = $partner_id == $selected_thread_id;
                        $unread_count = intval($conv['unread_count']);
                    ?>
                        <a href="?thread_id=<?php echo $partner_id; ?>" class="conversation-item <?php echo $is_active ? 'active' : ''; ?>" data-partner-id="<?php echo $partner_id; ?>">
                            <img src="<?php echo getProfilePhotoUrl($conv['profile_photo']); ?>" 
                                 alt="<?php echo htmlspecialchars($conv['full_name']); ?>" 
                                 class="profile-pic" 
                                 onerror="this.onerror=null;this.src='https://placehold.co/50x50/3498db/FFFFFF?text=U';">
                            <div class="conv-details">
                                <div class="conv-name"><?php echo htmlspecialchars($conv['full_name']); ?></div>
                                <div class="conv-last-msg"><?php echo htmlspecialchars(truncateMessage($conv['message_text'])); ?></div>
                            </div>
                            <div class="conv-time-unread">
                                <?php echo date('H:i', strtotime($conv['created_at'])); ?>
                                <?php if ($unread_count > 0): ?>
                                    <span class="unread-badge"><?php echo $unread_count; ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- 2. Chat Window Panel -->
                <div class="chat-window">
                    <div class="chat-header">
                        <i class="fas fa-arrow-left fa-lg mobile-only" id="backToConversations" style="cursor: pointer; margin-right: 15px; display: none;"></i>
                        <img src="<?php echo $selected_partner_photo; ?>" alt="Partner" class="profile-pic" style="margin-right: 1rem; width: 40px; height: 40px;" onerror="this.onerror=null;this.src='https://placehold.co/50x50/3498db/FFFFFF?text=P';">
                        <h3><?php echo $selected_partner_name; ?></h3>
                    </div>

                    <div class="chat-messages" id="chatMessages">
                        <?php if (!$selected_thread_id): ?>
                            <div class="empty-chat">
                                <i class="fas fa-comment-dots fa-3x" style="margin-bottom: 1rem;"></i>
                                <h3>No conversation selected</h3>
                                <p>Select a contact from the left panel to start chatting.</p>
                            </div>
                        <?php endif; ?>
                        <!-- Messages will be loaded here via AJAX -->
                    </div>
                    
                    <div id="chatMessageArea"></div>

                    <!-- Message Input Form -->
                    <div class="chat-input">
                        <form id="messageForm">
                            <input type="hidden" id="sender_id" name="sender_id" value="<?php echo $user_id; ?>">
                            <input type="hidden" id="receiver_id" name="receiver_id" value="<?php echo $selected_thread_id; ?>">
                            <input type="hidden" id="api_url" value="api/messages_endpoint.php">

                            <textarea id="messageText" name="message_text" placeholder="Type your message..." <?php echo $selected_thread_id ? '' : 'disabled'; ?> required></textarea>
                            <button type="submit" id="sendButton" <?php echo $selected_thread_id ? '' : 'disabled'; ?>>
                                <i class="fas fa-paper-plane"></i> Send
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
    
    <!-- Assuming this function exists in functions.php (or is a helper function defined earlier) -->
    <?php 
    /**
     * Truncates a message for display in the conversation list.
     * @param string $message
     * @return string
     */
    function truncateMessage(string $message): string {
        return strlen($message) > 30 ? substr($message, 0, 30) . '...' : $message;
    } 
    ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const userId = parseInt(document.getElementById('sender_id').value);
            const partnerId = parseInt(document.getElementById('receiver_id').value);
            const chatMessages = document.getElementById('chatMessages');
            const messageForm = document.getElementById('messageForm');
            const messageTextarea = document.getElementById('messageText');
            const chatMessageArea = document.getElementById('chatMessageArea');
            const backButton = document.getElementById('backToConversations');
            const conversationList = document.getElementById('conversationList');
            const apiUrl = document.getElementById('api_url').value;

            // --- Mobile View Handler ---
            const mediaQuery = window.matchMedia('(max-width: 900px)');
            function handleMobileView(e) {
                if (e.matches) {
                    // Mobile view
                    backButton.style.display = partnerId ? 'inline-block' : 'none';
                    if (partnerId) {
                        conversationList.classList.add('hidden-on-mobile');
                    } else {
                        conversationList.classList.remove('hidden-on-mobile');
                    }
                    backButton.onclick = () => {
                        window.location.href = 'messages.php';
                    };
                } else {
                    // Desktop view
                    backButton.style.display = 'none';
                    conversationList.classList.remove('hidden-on-mobile');
                }
            }
            mediaQuery.addEventListener('change', handleMobileView);
            handleMobileView(mediaQuery); // Initial check


            // --- Core Message Logic ---

            let lastMessageId = 0;
            let isPolling = false;
            let pollInterval;
            
            // Function to render a single message bubble
            function renderMessage(message, currentUserId) {
                const isSent = parseInt(message.sender_id) === currentUserId;
                const alignmentClass = isSent ? 'sent' : 'received';
                const time = new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                
                const bubble = document.createElement('div');
                bubble.className = `message-bubble ${alignmentClass}`;
                bubble.innerHTML = `
                    <p>${message.message_text}</p>
                    <span class="msg-time">${time}</span>
                `;
                chatMessages.appendChild(bubble);
                lastMessageId = Math.max(lastMessageId, message.message_id);
            }

            // Function to fetch and display messages
            async function fetchMessages(isInitialLoad = false) {
                if (!partnerId || isPolling) return;

                isPolling = true;
                if (isInitialLoad) {
                    chatMessageArea.innerHTML = '<div class="loader"></div> Loading messages...';
                }
                
                try {
                    const response = await fetch(apiUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'fetch_thread',
                            user_id: userId,
                            partner_id: partnerId,
                            last_id: isInitialLoad ? 0 : lastMessageId 
                        })
                    });

                    const data = await response.json();

                    if (data.status === 'success') {
                        chatMessageArea.innerHTML = ''; // Clear loading message

                        if (isInitialLoad) {
                            chatMessages.innerHTML = ''; // Clear previous messages
                            if (data.messages.length === 0) {
                                chatMessages.innerHTML = '<div class="empty-chat"><i class="fas fa-comments fa-3x" style="margin-bottom: 1rem;"></i><p>Start your conversation now!</p></div>';
                            }
                        }

                        data.messages.forEach(msg => renderMessage(msg, userId));
                        
                        // Scroll to the bottom on initial load or new messages
                        if (isInitialLoad || data.messages.length > 0) {
                            chatMessages.scrollTop = chatMessages.scrollHeight;
                        }
                    } else {
                        chatMessageArea.textContent = data.message || 'Error fetching messages.';
                    }

                } catch (error) {
                    console.error('Fetch error:', error);
                    chatMessageArea.textContent = 'Network error. Could not connect to chat.';
                } finally {
                    isPolling = false;
                }
            }
            
            // Function to send a new message
            async function sendMessage(e) {
                e.preventDefault();
                const messageText = messageTextarea.value.trim();
                if (!messageText || !partnerId) return;

                const sendButton = document.getElementById('sendButton');
                sendButton.disabled = true;
                messageTextarea.disabled = true;
                
                try {
                    const response = await fetch(apiUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'send_message',
                            sender_id: userId,
                            receiver_id: partnerId,
                            message_text: messageText,
                        })
                    });

                    const data = await response.json();

                    if (data.status === 'success') {
                        // Optimistically clear the input and force a fresh fetch to include the new message
                        messageTextarea.value = '';
                        await fetchMessages(); // Immediate fetch to update the thread
                        // Optionally update the conversation list here (refresh sidebar list)
                    } else {
                        chatMessageArea.textContent = data.message || 'Failed to send message.';
                    }
                } catch (error) {
                    console.error('Send error:', error);
                    chatMessageArea.textContent = 'Network error. Could not send message.';
                } finally {
                    sendButton.disabled = false;
                    messageTextarea.disabled = false;
                    messageTextarea.focus();
                }
            }

            // --- Initialization ---

            if (partnerId) {
                // Initial load
                fetchMessages(true); 

                // Start polling every 3 seconds
                pollInterval = setInterval(fetchMessages, 3000); 

                // Attach send listener
                messageForm.addEventListener('submit', sendMessage);

                // Stop polling when navigating away
                window.addEventListener('beforeunload', () => {
                    clearInterval(pollInterval);
                });
            } else {
                 // If no thread is selected, enable sending is disabled
                 document.getElementById('sendButton').disabled = true;
            }
        });
    </script>
</body>
</html>
