<?php
include 'session_check.php';
include 'db_connect.php';
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// Handle status update action
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = intval($_GET['id']);
    
    if ($action == 'status' && isset($_GET['new_status'])) {
        $new_status = $_GET['new_status'];
        $stmt = $conn->prepare("UPDATE inquiries SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $id);
        $stmt->execute();
    } elseif ($action == 'delete') {
        $stmt = $conn->prepare("DELETE FROM inquiries WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    header("Location: admin_inquiries_new.php");
    exit();
}

$inquiries_result = $conn->query("SELECT * FROM inquiries ORDER BY 
    CASE status 
        WHEN 'new' THEN 1 
        WHEN 'in_progress' THEN 2 
        WHEN 'responded' THEN 3 
        WHEN 'closed' THEN 4 
    END, 
    received_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Customer Inquiries</title>
<link rel="stylesheet" href="CSS/admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<link href="https://fonts.googleapis.com/css?family=Poppins" rel="stylesheet">
<style>
  :root {
      --primary-color: #B95A4B; /* Updated to Cafe Theme */
      --primary-dark: #9C4538;
      --main-bg: #f8f8fb;
      --card-bg: #ffffff;
      --text-color: #495057;
      --subtle-text: #74788d;
      --border-color: #eff2f7;
      --green-accent: #34c38f;
      --red-accent: #f46a6a;
      --blue-accent: #556ee6;
      --yellow-accent: #f1b44c;
  }
  .main-content { background-color: var(--main-bg); }
  .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
  .page-header h1 { font-size: 1.5rem; color: var(--text-color); margin: 0; }

  .inquiries-list { display: flex; flex-direction: column; gap: 20px; }

  .inquiry-card { background: var(--card-bg); border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-left: 4px solid transparent; transition: transform 0.2s; }
  .inquiry-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
  
  .inquiry-card.status-new { border-left-color: var(--red-accent); }
  .inquiry-card.status-in_progress { border-left-color: var(--yellow-accent); }
  .inquiry-card.status-responded { border-left-color: var(--green-accent); }
  .inquiry-card.status-closed { border-left-color: var(--subtle-text); }

  .inquiry-header { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-bottom: 1px solid var(--border-color); }
  .inquiry-sender { font-weight: 600; color: var(--text-color); font-size: 1.05rem; }
  .inquiry-sender .email { font-weight: 400; color: var(--primary-color); font-size: 0.9rem; margin-left: 8px; }
  .inquiry-meta { display: flex; gap: 15px; align-items: center; }
  .inquiry-time { font-size: 0.85rem; color: var(--subtle-text); }

  .status-badge { padding: 5px 12px; border-radius: 20px; font-weight: 600; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 5px; text-transform: uppercase; }
  .status-badge.new { background-color: #ffebee; color: #c62828; }
  .status-badge.in_progress { background-color: #fff3e0; color: #e65100; }
  .status-badge.responded { background-color: #e8f5e9; color: #2e7d32; }
  .status-badge.closed { background-color: #f5f5f5; color: #616161; }

  .inquiry-body { padding: 20px; color: var(--text-color); position: relative; }
  
  /* --- Compact Message UI --- */
  .message-content {
      font-size: 0.95rem;
      line-height: 1.6;
      display: -webkit-box;
      -webkit-line-clamp: 5; /* Max 5 lines */
      -webkit-box-orient: vertical;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: pre-wrap;
  }
  .message-content.expanded {
      display: block;
      -webkit-line-clamp: unset;
  }
  .read-more-btn {
      background: none;
      border: none;
      color: var(--blue-accent);
      font-size: 0.85rem;
      font-weight: 600;
      cursor: pointer;
      padding: 0;
      margin-top: 10px;
      display: none; /* Shown via JS if needed */
  }
  .read-more-btn:hover { text-decoration: underline; }

  .response-section { padding: 15px 20px; background: #f8f9fa; border-top: 1px solid var(--border-color); }
  .response-label { font-size: 0.85rem; font-weight: 700; color: var(--text-color); margin-bottom: 8px; text-transform: uppercase; }
  .response-text { background: white; padding: 15px; border-radius: 6px; border-left: 4px solid var(--green-accent); font-size: 0.95rem; }
  .response-meta { font-size: 0.8rem; color: var(--subtle-text); margin-top: 8px; text-align: right; font-style: italic; }

  .internal-notes { padding: 10px 20px; background: #fff9e6; border-top: 1px solid var(--border-color); color: #856404; font-size: 0.9rem; }

  .inquiry-actions { display: flex; gap: 10px; padding: 15px 20px; flex-wrap: wrap; border-top: 1px solid var(--border-color); background: #fcfcfc; border-radius: 0 0 8px 8px; }
  .btn-action { padding: 8px 16px; border-radius: 4px; border: none; font-weight: 500; cursor: pointer; transition: all 0.2s; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
  .btn-action.reply { background: var(--blue-accent); color: white; }
  .btn-action.reply:hover { background: #4058c5; }
  .btn-action.status { background: white; color: var(--text-color); border: 1px solid #ced4da; }
  .btn-action.status:hover { border-color: var(--text-color); }
  .btn-action.delete { background: white; color: var(--red-accent); border: 1px solid var(--red-accent); margin-left: auto; }
  .btn-action.delete:hover { background: var(--red-accent); color: white; }

  /* Modal */
  .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; backdrop-filter: blur(3px); }
  .modal.active { display: flex; }
  .modal-container { background: white; border-radius: 12px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; box-shadow: 0 15px 50px rgba(0,0,0,0.3); animation: slideUp 0.3s ease; }
  .modal-header { padding: 20px 24px; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center; background: #f8f9fa; border-radius: 12px 12px 0 0; }
  .modal-header h2 { font-size: 1.1rem; font-weight: 700; color: var(--text-color); margin: 0; }
  .close-modal { background: none; border: none; font-size: 1.5rem; color: #999; cursor: pointer; transition: color 0.2s; }
  .close-modal:hover { color: var(--red-accent); }
  .modal-body { padding: 24px; }
  
  .form-group { margin-bottom: 20px; }
  .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: var(--text-color); font-size: 0.9rem; }
  .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ced4da; border-radius: 6px; font-size: 0.95rem; font-family: inherit; resize: vertical; min-height: 150px; transition: border 0.2s; }
  .form-group textarea:focus { border-color: var(--blue-accent); outline: none; }
  .form-group select { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 6px; font-size: 0.95rem; }

  .quick-responses { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 15px; }
  .quick-response-btn { padding: 6px 12px; border: 1px solid #dee2e6; background: #f8f9fa; border-radius: 20px; font-size: 0.8rem; cursor: pointer; transition: all 0.2s; color: var(--text-color); }
  .quick-response-btn:hover { background: var(--blue-accent); color: white; border-color: var(--blue-accent); }
  
  .original-inquiry { background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 3px solid var(--primary-color); font-size: 0.9rem; color: #666; }
  
  .button-group { display: flex; gap: 12px; margin-top: 24px; }
  .btn-modal { flex: 1; padding: 12px; border-radius: 6px; font-size: 1rem; font-weight: 600; cursor: pointer; border: none; transition: 0.2s; }
  .btn-submit { background: var(--blue-accent); color: white; }
  .btn-submit:hover { background: #4058c5; }
  .btn-cancel { background: #f1f3f5; color: var(--text-color); }
  .btn-cancel:hover { background: #e2e6ea; }

  .alert { padding: 15px; margin-bottom: 25px; border-radius: 8px; font-size: 0.9rem; display: flex; align-items: center; gap: 10px; }
  .alert-success { background: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
  .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
  
  .no-inquiries { background: var(--card-bg); padding: 60px; border-radius: 12px; text-align: center; color: var(--subtle-text); border: 1px solid var(--border-color); }
  
  @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>
</head>
<body>
<div class="admin-container">
  <?php include 'admin_sidebar.php'; ?>
  <main class="main-content">
    <header class="page-header">
      <h1><i class="fas fa-envelope-open-text"></i> Customer Inquiries</h1>
    </header>

    <?php if (isset($_GET['success'])): ?>
      <div class="alert alert-success">
        <?php 
        if ($_GET['success'] === 'response_sent') {
            echo '<i class="fas fa-check-circle"></i> Response sent successfully and email notification delivered!';
        } elseif ($_GET['success'] === 'response_saved') {
            echo '<i class="fas fa-check-circle"></i> Response saved successfully!';
        }
        ?>
      </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['warning']) && $_GET['warning'] === 'email_failed'): ?>
      <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> Response was saved to database, but email notification failed to send.
      </div>
    <?php endif; ?>

    <div class="inquiries-list">
        <?php if ($inquiries_result->num_rows > 0): ?>
            <?php while ($row = $inquiries_result->fetch_assoc()): ?>
                <div class="inquiry-card status-<?php echo $row['status']; ?>">
                    <div class="inquiry-header">
                        <div class="inquiry-sender">
                            <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                            <span class="email">&lt;<?php echo htmlspecialchars($row['email']); ?>&gt;</span>
                        </div>
                        <div class="inquiry-meta">
                            <span class="status-badge <?php echo $row['status']; ?>">
                                <?php 
                                $labels = ['new'=>'New','in_progress'=>'In Progress','responded'=>'Responded','closed'=>'Closed'];
                                echo $labels[$row['status']] ?? $row['status'];
                                ?>
                            </span>
                            <span class="inquiry-time"><?php echo date("M d, Y • h:i A", strtotime($row['received_at'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="inquiry-body">
                        <!-- Truncated Message Logic -->
                        <div class="message-content" id="msg-<?php echo $row['id']; ?>">
                            <?php echo nl2br(htmlspecialchars($row['message'])); ?>
                        </div>
                        <button class="read-more-btn" id="btn-<?php echo $row['id']; ?>" onclick="toggleMessage(<?php echo $row['id']; ?>)">Read More <i class="fas fa-chevron-down"></i></button>
                    </div>
                    
                    <?php if (!empty($row['admin_response'])): ?>
                    <div class="response-section">
                        <div class="response-label"><i class="fas fa-reply"></i> Admin Response</div>
                        <div class="response-text"><?php echo nl2br(htmlspecialchars($row['admin_response'])); ?></div>
                        <div class="response-meta">
                            Replied on <?php echo date("M d, Y h:i A", strtotime($row['responded_at'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($row['internal_notes'])): ?>
                    <div class="internal-notes">
                        <i class="fas fa-sticky-note"></i> <strong>Note:</strong> <?php echo nl2br(htmlspecialchars($row['internal_notes'])); ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="inquiry-actions">
                        <button onclick="openReplyModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['email'], ENT_QUOTES); ?>', <?php echo htmlspecialchars(json_encode($row['message']), ENT_QUOTES); ?>, '<?php echo $row['status']; ?>')" class="btn-action reply">
                            <i class="fas fa-reply"></i> Reply
                        </button>
                        
                        <select onchange="if(this.value) window.location.href='?action=status&id=<?php echo $row['id']; ?>&new_status=' + this.value" class="btn-action status">
                            <option value="">Change Status</option>
                            <option value="in_progress" <?php if($row['status']=='in_progress') echo 'selected'; ?>>In Progress</option>
                            <option value="responded" <?php if($row['status']=='responded') echo 'selected'; ?>>Responded</option>
                            <option value="closed" <?php if($row['status']=='closed') echo 'selected'; ?>>Closed</option>
                        </select>
                        
                        <a href="?action=delete&id=<?php echo $row['id']; ?>" class="btn-action delete" onclick="return confirm('Delete this inquiry permanently?')">
                            <i class="fas fa-trash-alt"></i> Delete
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-inquiries">
                <i class="fas fa-inbox" style="font-size: 50px; margin-bottom: 20px; display: block; opacity: 0.3;"></i>
                <p>No inquiries found. Great job!</p>
            </div>
        <?php endif; ?>
    </div>
  </main>
</div>

<!-- Reply Modal -->
<div id="replyModal" class="modal">
    <div class="modal-container">
        <div class="modal-header">
            <h2>Reply to Inquiry</h2>
            <button class="close-modal" onclick="closeReplyModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="original-inquiry">
                <strong>To:</strong> <span id="modalCustomerName"></span> (<span id="modalCustomerEmail"></span>)<br>
                <div style="margin-top:8px; padding-top:8px; border-top:1px dashed #ddd;">
                    <small>Original Message:</small><br>
                    <span id="modalOriginalMessage" style="font-style:italic;"></span>
                </div>
            </div>
            
            <form method="POST" action="send_inquiry_response.php">
                <input type="hidden" name="inquiry_id" id="modalInquiryId">
                
                <div class="form-group">
                    <label>Templates:</label>
                    <div class="quick-responses">
                        <button type="button" class="quick-response-btn" onclick="insertTemplate('Thank you for your inquiry. We will get back to you within 24 hours.')">24hr Response</button>
                        <button type="button" class="quick-response-btn" onclick="insertTemplate('We have confirmed your reservation. We look forward to seeing you!')">Reservation Confirmed</button>
                        <button type="button" class="quick-response-btn" onclick="insertTemplate('Thank you for your feedback! We appreciate you taking the time to write to us.')">Feedback Thanks</button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Your Response (Sent via Email): *</label>
                    <textarea name="response_message" id="responseMessage" required placeholder="Type your reply here..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Internal Note (Optional):</label>
                    <textarea name="internal_notes" id="internalNotes" placeholder="Private notes for staff..." style="min-height: 80px;"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Status Update:</label>
                    <select name="status" id="modalStatus">
                        <option value="in_progress">In Progress</option>
                        <option value="responded" selected>Responded</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn-modal btn-submit"><i class="fas fa-paper-plane"></i> Send Email Reply</button>
                    <button type="button" onclick="closeReplyModal()" class="btn-modal btn-cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleMessage(id) {
    var content = document.getElementById('msg-' + id);
    var btn = document.getElementById('btn-' + id);
    
    if (content.classList.contains('expanded')) {
        content.classList.remove('expanded');
        btn.innerHTML = 'Read More <i class="fas fa-chevron-down"></i>';
    } else {
        content.classList.add('expanded');
        btn.innerHTML = 'Show Less <i class="fas fa-chevron-up"></i>';
    }
}

// Initialize Read More buttons based on content length
document.addEventListener("DOMContentLoaded", function() {
    var contents = document.querySelectorAll('.message-content');
    contents.forEach(function(content) {
        // Check if actual height exceeds the 5-line limit height (approx 7.5em or 120px)
        if (content.scrollHeight > content.clientHeight) {
            var btnId = content.id.replace('msg-', 'btn-');
            document.getElementById(btnId).style.display = 'inline-block';
        }
    });
});

function openReplyModal(id, name, email, msg, status) {
    document.getElementById('modalInquiryId').value = id;
    document.getElementById('modalCustomerName').textContent = name;
    document.getElementById('modalCustomerEmail').textContent = email;
    document.getElementById('modalOriginalMessage').textContent = msg; // Use textContent for security
    
    document.getElementById('responseMessage').value = ''; // Clear previous
    document.getElementById('internalNotes').value = '';
    
    // Default to responded
    document.getElementById('modalStatus').value = 'responded';
    
    document.getElementById('replyModal').classList.add('active');
}

function closeReplyModal() {
    document.getElementById('replyModal').classList.remove('active');
}

function insertTemplate(text) {
    document.getElementById('responseMessage').value = text;
}

// Close modal when clicking outside
document.getElementById('replyModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeReplyModal();
    }
});
</script>
</body>
</html>
<?php $conn->close(); ?>