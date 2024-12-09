<?php
// start session for user management
session_start();

// database connection
function connectDb() 
{
    $servername = "localhost";
    $username = "ngessner";
    $password = "Hellome1029!";
    $dbname = "clickerDb";

    // create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // check connection
    if ($conn->connect_error) 
    {
        die(json_encode(["status" => "error", "message" => "connection failed: " . $conn->connect_error]));
    }
    return $conn;
}

// add a new user
function addUser($username, $firstName, $lastName, $password) 
{
    $conn = connectDb();
    $sql = "INSERT INTO users (username, first_name, last_name, password) VALUES ('$username', '$firstName', '$lastName', '$password')";

    // execute query
    if ($conn->query($sql) === TRUE) 
    {
        echo json_encode(["status" => "success", "message" => "user registered successfully"]);
    } 
    else 
    {
        // handle duplicate usernames
        if ($conn->errno === 1062) 
        {
            echo json_encode(["status" => "error", "message" => "username already exists"]);
        } 
        else 
        {
            echo json_encode(["status" => "error", "message" => "error: " . $conn->error]);
        }
    }

    $conn->close();
}

// authenticate a user
function authenticateUser($username, $password) 
{
    $conn = connectDb();

    $sql = "SELECT user_id, username, first_name, last_name, password FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) 
    {
        echo json_encode(["status" => "error", "message" => "sql prep failed: " . $conn->error]);
        return;
    }

    // bind and execute statement
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($userId, $dbUsername, $firstName, $lastName, $dbPassword);
    $stmt->fetch();

    // verify password
    if ($dbPassword && $dbPassword === $password) 
    {
        // save user data in session
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $dbUsername;
        $_SESSION['first_name'] = $firstName;
        $_SESSION['last_name'] = $lastName;
        $_SESSION['click_total'] = null; // initialize as null
        $_SESSION['clicks_per_sec'] = null; // initialize as null
        $_SESSION['date_scored'] = null; // initialize as null

        echo json_encode([
            "status" => "success",
            "message" => "login successful",
            "user" => [
                "user_id" => $userId,
                "username" => $dbUsername,
                "first_name" => $firstName,
                "last_name" => $lastName,
                "click_total" => null,
                "clicks_per_sec" => null,
                "date_scored" => null,
            ],
        ]);
    } 
    else 
    {
        echo json_encode(["status" => "error", "message" => "invalid username or password"]);
    }

    $stmt->close();
    $conn->close();
}

// save a score
function saveScore($userId, $clickTotal, $clicksPerSecond) 
{
    $conn = connectDb();

    $sql = "INSERT INTO scores (user_id, click_total, clicks_per_sec, date_scored) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) 
    {
        echo json_encode(["status" => "error", "message" => "sql prep failed: " . $conn->error]);
        return;
    }

    $stmt->bind_param("iid", $userId, $clickTotal, $clicksPerSecond);

    if ($stmt->execute()) 
    {
        echo json_encode(["status" => "success", "message" => "score saved"]);
    } 
    else 
    {
        echo json_encode(["status" => "error", "message" => $stmt->error]);
    }

    $stmt->close();
    $conn->close();
}

// retrieve high scores
function getHighScores($userId = null) 
{
    $conn = connectDb();

    if ($userId) 
    {
        // query for user-specific scores
        $sql = "SELECT u.first_name, u.last_name, s.click_total, s.clicks_per_sec, s.date_scored 
                FROM scores s
                JOIN users u ON s.user_id = u.user_id
                WHERE s.user_id = ?
                ORDER BY s.date_scored DESC";
        $stmt = $conn->prepare($sql);

        if ($stmt === false) 
        {
            echo json_encode(["status" => "error", "message" => "sql prep failed: " . $conn->error]);
            return;
        }

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
    } 
    else 
    {
        // query for top scores
        $sql = "SELECT u.first_name, u.last_name, s.click_total, s.clicks_per_sec, s.date_scored 
                FROM scores s
                JOIN users u ON s.user_id = u.user_id
                ORDER BY s.clicks_per_sec DESC
                LIMIT 10";
        $result = $conn->query($sql);
    }

    $scores = [];
    while ($row = $result->fetch_assoc()) 
    {
        $scores[] = $row;
    }

    echo json_encode(["status" => "success", "scores" => $scores]);

    if (isset($stmt)) 
    {
        $stmt->close();
    }
    $conn->close();
}

// handle incoming requests
if (isset($_GET['action'])) 
{
    $action = $_GET['action'];

    // handle register action
    if ($action === 'register') 
    {
        if (isset($_POST['username'], $_POST['first_name'], $_POST['last_name'], $_POST['password'])) 
        {
            addUser($_POST['username'], $_POST['first_name'], $_POST['last_name'], $_POST['password']);
        } 
        else 
        {
            echo json_encode(["status" => "error", "message" => "missing required fields"]);
        }
    } 
    // handle login action
    elseif ($action === 'login') 
    {
        if (isset($_POST['username'], $_POST['password'])) 
        {
            authenticateUser($_POST['username'], $_POST['password']);
        } 
        else 
        {
            echo json_encode(["status" => "error", "message" => "missing required fields"]);
        }
    } 
    // handle save score action
    elseif ($action === 'saveScore') 
    {
        if (isset($_SESSION['user_id'])) 
        {
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['click_total'], $data['clicks_per_sec'])) 
            {
                saveScore($_SESSION['user_id'], $data['click_total'], $data['clicks_per_sec']);
            } 
            else 
            {
                echo json_encode(["status" => "error", "message" => "missing score data"]);
            }
        } 
        else 
        {
            echo json_encode(["status" => "error", "message" => "user not logged in"]);
        }
    } 
    // handle get high scores action
    elseif ($action === 'getHighScores') 
    {
        getHighScores($_SESSION['user_id'] ?? null);
    } 
    // handle get session data action
    elseif ($action === 'getSessionData') 
    {
        if (isset($_SESSION['user_id'])) 
        {
            foreach ($_SESSION as $key => $value) 
            {
                echo "$key: $value\n";
            }
        } 
        else 
        {
            die("user not loged in");
        }
    } 
    else 
    {
        // handle invalid action
        die("invalid or missing action");
    }
} 
else 
{
    echo json_encode(["status" => "error", "message" => "no action specified"]);
}
?>
