<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "User not found.";
    exit();
}

// Default profile pic if empty
$profilePic = !empty($user['profile_picture']) ? $user['profile_picture'] : 'https://ui-avatars.com/api/?name='.urlencode($user['fullname']).'&background=A05E44&color=fff&size=128';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Cafe Emmanuel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #A05E44;       /* Caramel */
            --secondary: #2C1E16;     /* Espresso */
            --accent: #D4A373;        /* Latte */
            --bg: #F8F4EE;            /* Cream */
            --white: #FFFFFF;
            --font-heading: 'Playfair Display', serif;
            --font-body: 'Poppins', sans-serif;
            --shadow: 0 20px 40px rgba(44, 30, 22, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: var(--font-body); 
            background: var(--bg); 
            color: var(--text-dark); 
            overflow-x: hidden;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* --- Parallax Background --- */
        .page-bg {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: linear-gradient(rgba(248, 244, 238, 0.92), rgba(248, 244, 238, 0.92)), url('Cover-Photo.jpg');
            background-size: cover;
            background-attachment: fixed;
            z-index: -1;
        }

        .container {
            max-width: 900px;
            margin: 60px auto;
            padding: 20px;
            width: 100%;
        }

        /* --- Hanging Picture Logic --- */
        .profile-header {
            text-align: center;
            margin-bottom: 50px;
            position: relative;
        }

        .hanging-frame {
            position: relative;
            display: inline-block;
            padding: 10px;
            background: #fff;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 8px solid #fff;
            transform-origin: top center;
            animation: dropAndSway 1.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
            z-index: 10;
        }

        /* The strings/wire holding the picture */
        .hanging-frame::before, .hanging-frame::after {
            content: '';
            position: absolute;
            top: -60px;
            width: 2px;
            height: 60px;
            background: #555;
        }
        .hanging-frame::before { left: 20%; transform: rotate(-10deg); }
        .hanging-frame::after { right: 20%; transform: rotate(10deg); }

        .profile-img {
            width: 180px;
            height: 180px;
            object-fit: cover;
            display: block;
        }

        @keyframes dropAndSway {
            0% { transform: translateY(-200px) rotate(0deg); opacity: 0; }
            60% { transform: translateY(0) rotate(5deg); opacity: 1; }
            80% { transform: rotate(-3deg); }
            100% { transform: rotate(0deg); }
        }

        .sway-loop {
            animation: gentleSway 4s ease-in-out infinite alternate;
        }

        @keyframes gentleSway {
            from { transform: rotate(-1.5deg); }
            to { transform: rotate(1.5deg); }
        }

        /* --- Details Card --- */
        .profile-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(15px);
            border-radius: var(--radius-lg);
            padding: 50px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(160, 94, 68, 0.2);
            position: relative;
            overflow: hidden;
            transform-style: preserve-3d;
            perspective: 1000px;
            animation: fadeInUp 1s ease-out;
        }

        .profile-card::after {
            content: '';
            position: absolute;
            top: -50%; right: -50%;
            width: 300px; height: 300px;
            background: radial-gradient(circle, var(--primary-glow) 0%, transparent 70%);
            opacity: 0.4;
        }

        .profile-title {
            font-family: var(--font-heading);
            font-size: 2.5rem;
            color: var(--secondary);
            margin-bottom: 30px;
            text-align: center;
        }

        /* --- Form Styling --- */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        .info-group {
            position: relative;
            margin-bottom: 10px;
        }

        .info-group label {
            display: block;
            font-size: 0.85rem;
            color: var(--primary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
            margin-left: 5px;
        }

        .info-value {
            width: 100%;
            padding: 14px 20px;
            background: rgba(255, 255, 255, 0.6);
            border: 2px solid #E6DCD3;
            border-radius: 12px;
            font-size: 1rem;
            color: var(--secondary);
            transition: all 0.3s;
        }

        .info-value:focus {
            outline: none;
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 15px var(--primary-glow);
            transform: translateY(-2px);
        }

        /* --- Floating Icons --- */
        .anim-icon {
            position: absolute;
            color: var(--primary);
            opacity: 0.1;
            z-index: 1;
            pointer-events: none;
            animation: floatIcon 10s infinite ease-in-out alternate;
        }
        @keyframes floatIcon {
            from { transform: translate(0, 0) rotate(0deg); }
            to { transform: translate(30px, 40px) rotate(20deg); }
        }

        .btn-container {
            grid-column: 1 / -1;
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .btn-action {
            flex: 1;
            padding: 15px;
            border-radius: 50px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.4s;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-edit {
            background: var(--primary);
            color: white;
            box-shadow: 0 8px 20px var(--primary-glow);
        }

        .btn-edit:hover {
            background: var(--secondary);
            transform: translateY(-3px);
        }

        .btn-back {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }

        .btn-back:hover {
            background: var(--primary);
            color: white;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .info-grid { grid-template-columns: 1fr; }
            .profile-card { padding: 30px 20px; }
        }
    </style>
</head>
<body>

    <div class="page-bg"></div>

    <i class="fas fa-coffee anim-icon" style="top: 10%; left: 10%; font-size: 5rem;"></i>
    <i class="fas fa-leaf anim-icon" style="bottom: 10%; right: 10%; font-size: 4rem; animation-delay: -2s;"></i>
    <i class="fas fa-mug-hot anim-icon" style="top: 40%; right: 15%; font-size: 3rem; animation-delay: -5s;"></i>

    <div class="container">
        
        <div class="profile-header">
            <div class="hanging-frame" id="profileFrame">
                <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="Profile Picture" class="profile-img">
            </div>
        </div>

        <div class="profile-card" id="tiltCard">
            <h1 class="profile-title">Profile Settings</h1>
            
            <form action="update_profile.php" method="POST" enctype="multipart/form-data">
                <div class="info-grid">
                    <div class="info-group">
                        <label>Full Name</label>
                        <input type="text" class="info-value" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" readonly>
                    </div>

                    <div class="info-group">
                        <label>Username</label>
                        <input type="text" class="info-value" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                    </div>

                    <div class="info-group">
                        <label>Email Address</label>
                        <input type="email" class="info-value" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                    </div>

                    <div class="info-group">
                        <label>Contact Number</label>
                        <input type="text" class="info-value" name="contact" value="<?php echo htmlspecialchars($user['contact'] ?? 'N/A'); ?>" readonly>
                    </div>

                    <div class="info-group" style="grid-column: 1 / -1;">
                        <label>Change Profile Picture</label>
                        <input type="file" class="info-value" name="profile_pic" accept="image/*">
                    </div>

                    <div class="btn-container">
                        <a href="index.php" class="btn-action btn-back">
                            <i class="fas fa-arrow-left"></i> Home
                        </a>
                        <button type="submit" class="btn-action btn-edit">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // --- 1. 3D Tilt Effect for Card ---
        const tiltCard = document.getElementById('tiltCard');
        document.addEventListener('mousemove', (e) => {
            if (window.innerWidth < 992) return;
            
            const rect = tiltCard.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            
            const rotateX = ((y - centerY) / centerY) * -5; // Max 5 deg
            const rotateY = ((x - centerX) / centerX) * 5;
            
            tiltCard.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
        });

        tiltCard.addEventListener('mouseleave', () => {
            tiltCard.style.transform = `perspective(1000px) rotateX(0deg) rotateY(0deg)`;
            tiltCard.style.transition = 'transform 0.5s ease';
        });

        // --- 2. Parallax Icons ---
        window.addEventListener('scroll', () => {
            const icons = document.querySelectorAll('.anim-icon');
            icons.forEach((icon, index) => {
                const speed = (index + 1) * 0.2;
                icon.style.transform = `translateY(${window.scrollY * speed}px)`;
            });
        });

        // --- 3. Start Swaying after the drop animation ends ---
        const frame = document.getElementById('profileFrame');
        setTimeout(() => {
            frame.classList.add('sway-loop');
        }, 1500);
    </script>
</body>
</html>