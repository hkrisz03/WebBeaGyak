<?php
session_start();

// Adatbázis kapcsolat
$servername = "mysql.omega";
$port = 3306;
$username = "regisztracio";
$password = "regisztracio";
$dbname = "regisztracio";

// Csatlakozás az adatbázishoz
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

if (getenv('APP_ENV') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}

$error = '';
$formData = ['felhasznalonev' => '', 'email' => ''];
$isRegistering = isset($_POST['register']);

// Handle login attempt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isRegistering) {
    $login_felhasznalonev = trim($_POST['login_felhasznalonev'] ?? '');
    $login_jelszo = $_POST['login_jelszo'] ?? '';
    
    if (empty($login_felhasznalonev) || empty($login_jelszo)) {
        $error = "Felhasználónév és jelszó megadása kötelező!";
    } else {
        try {
            // Módosított SQL, most már az is_admin mezőt is lekérjük
            $stmt = $conn->prepare("SELECT id, felhasznalonev, jelszo, role FROM felhasznalok WHERE felhasznalonev = ? AND active = TRUE");
            if (!$stmt) {
                throw new Exception("SQL hiba: " . $conn->error);
            }
            
            $stmt->bind_param("s", $login_felhasznalonev);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                if (password_verify($login_jelszo, $user['jelszo'])) {
                    $_SESSION['loggedin'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['felhasznalonev'] = $user['felhasznalonev'];
                    $_SESSION['role'] = $user['role']; // Admin státusz mentése
                    $_SESSION['last_activity'] = time();
                    
                    // Egyéni üdvözlő üzenet admin/nem admin esetén
                    if ($user['role']==1) {
                        $_SESSION['welcome_message'] = "Üdvözöljük, Admin " . "!";
                    } else {
                        $_SESSION['welcome_message'] = "Üdvözlöm " . htmlspecialchars($user['felhasznalonev']) . "!";
                    }

                    $stmt->close();
                    header("Location: fooldal.php");
                    exit();
                } else {
                    $error = "Hibás felhasználónév vagy jelszó!";
                }
            } else {
                $error = "Hibás felhasználónév vagy jelszó!";
            }
            
            $stmt->close();
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Handle registration attempt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isRegistering) {
    $formData['felhasznalonev'] = trim($_POST['reg_felhasznalonev'] ?? '');
    $formData['email'] = trim($_POST['reg_email'] ?? '');
    $jelszo = $_POST['reg_jelszo'] ?? '';
    $jelszo_ujra = $_POST['reg_jelszo_ujra'] ?? '';

    // Validation
    $errors = [];

    // Kötelező mezők ellenőrzése
    if (empty($formData['felhasznalonev'])) {
        $errors[] = "Felhasználónév megadása kötelező!";
    }
    if (empty($formData['email'])) {
        $errors[] = "Email cím megadása kötelező!";
    }
    if (empty($jelszo)) {
        $errors[] = "Jelszó megadása kötelező!";
    }

    if (empty($errors)) {
        try {
            // Felhasználónév egyediségének ellenőrzése
            $check_stmt = $conn->prepare("SELECT id FROM felhasznalok WHERE felhasznalonev = ?");
            if (!$check_stmt) {
                throw new Exception("SQL hiba: " . $conn->error);
            }

            $check_stmt->bind_param("s", $formData['felhasznalonev']);
            $check_stmt->execute();
            $check_stmt->store_result();

            if ($check_stmt->num_rows > 0) {
                $errors[] = "A felhasználónév már foglalt!";
            } else {
                // Jelszó hashelése
                $jelszo_hash = password_hash($jelszo, PASSWORD_DEFAULT);

                // Új felhasználó beszúrása
                $insert_stmt = $conn->prepare("INSERT INTO felhasznalok (felhasznalonev, email, jelszo, active) VALUES (?, ?, ?, TRUE)");
                if (!$insert_stmt) {
                    throw new Exception("SQL hiba: " . $conn->error);
                }

                $insert_stmt->bind_param("sss", $formData['felhasznalonev'], $formData['email'], $jelszo_hash);

                if ($insert_stmt->execute()) {
                    $_SESSION['reg_success'] = "Sikeres regisztráció! Most már bejelentkezhet.";
                    $insert_stmt->close();
                    $check_stmt->close();
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    $errors[] = "Hiba történt a regisztráció során: " . $insert_stmt->error;
                }
            }

            $check_stmt->close();
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $error = implode("<br>", $errors);
        $isRegistering = true;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bejelentkezés - Vaszilij EDC</title>
    <link rel="stylesheet" href="style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="icon" type="image/png" href="https://vaszilijedc.hu/wp-content/uploads/2018/05/Vaszilij-EDC.jpg.webp">
</head>
<body>
    <div class="form-container">
        <!-- Login Form -->
        <div id="login-form" class="form-section" style="<?php echo $isRegistering ? 'display:none;' : ''; ?>">
            <form method="POST" action="">
                <h1>Bejelentkezés</h1>

                <?php if (isset($_SESSION['reg_success'])): ?>
                    <div class="alert success"><?= htmlspecialchars($_SESSION['reg_success']) ?></div>
                    <?php unset($_SESSION['reg_success']); ?>
                <?php endif; ?>

                <?php if (!empty($error) && !$isRegistering): ?>
                    <div class="alert error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="input-box">
                    <input type="text" name="login_felhasznalonev" placeholder="Felhasználónév" required
                           value="<?= htmlspecialchars($_POST['login_felhasznalonev'] ?? '') ?>">
                    <i class='bx bx-user'></i>
                </div>
                <div class="input-box">
                    <input type="password" name="login_jelszo" placeholder="Jelszó" required>
                    <i class='bx bxs-lock-alt'></i>
                </div>

                <button type="submit" class="btn">Bejelentkezés</button>

                <div class="register-link">
                    <p>Nincs fiókod? <button type="button" class="link-btn" onclick="showRegister()">Regisztrálj</button></p>
                </div>
            </form>
        </div>

        <!-- Registration Form -->
        <div id="register-form" class="form-section" style="<?php echo !$isRegistering ? 'display:none;' : ''; ?>">
            <form method="POST" action="">
                <input type="hidden" name="register" value="1">
                <h1>Regisztráció</h1>

                <?php if (!empty($error) && $isRegistering): ?>
                    <div class="alert error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="input-box">
                    <input type="text" name="reg_felhasznalonev" placeholder="Felhasználónév" required
                           value="<?= htmlspecialchars($formData['felhasznalonev']) ?>"
                           pattern="[a-zA-Z0-9_]+" title="Csak betűk, számok és alulvonás">
                    <i class='bx bx-user'></i>
                </div>
                <div class="input-box">
                    <input type="email" name="reg_email" placeholder="Email cím" required
                           value="<?= htmlspecialchars($formData['email']) ?>">
                    <i class='bx bx-envelope'></i>
                </div>
                <div class="input-box">
                    <input type="password" name="reg_jelszo" placeholder="Jelszó" required
                           minlength="4" title="Legalább 4 karakter">
                    <i class='bx bxs-lock-alt'></i>
                </div>
                <div class="input-box">
                    <input type="password" name="reg_jelszo_ujra" placeholder="Jelszó újra" required>
                    <i class='bx bxs-lock-alt'></i>
                </div>

                <button type="submit" class="btn">Regisztráció</button>

                <div class="register-link">
                    <p>Már van fiókod? <button type="button" class="link-btn" onclick="showLogin()">Bejelentkezés</button></p>
                </div>
            </form>
        </div>
    </div>

   <script>
    function showLogin() {
    document.getElementById('login-form').style.display = 'block';
    document.getElementById('register-form').style.display = 'none';
    // Ne rejtsük el a hibaüzeneteket automatikusan
}

function showRegister() {
    document.getElementById('login-form').style.display = 'none';
    document.getElementById('register-form').style.display = 'block';
    // Ne rejtsük el a hibaüzeneteket automatikusan
}

document.addEventListener("DOMContentLoaded", function() {
    // Alapértelmezett megjelenítés
    if (window.location.search.includes('register=1')) {
        showRegister();
    } else {
        showLogin();
    }
});
</script>

</body>
</html>