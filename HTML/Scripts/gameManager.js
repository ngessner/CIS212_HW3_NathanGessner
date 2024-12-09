// Game Script
let clickCount = 0;
let timeLeft = 5;
let gameInterval;

const clickButton = document.getElementById("clickButton");
const restartButton = document.getElementById("restartButton");
const clickCountDisplay = document.getElementById("clickCount");
const timerDisplay = document.getElementById("timer");
const gameMessage = document.getElementById("gameMessage");

// Start Game
function startGame() {
    clickCount = 0;
    timeLeft = 5;
    clickCountDisplay.textContent = "Clicks: 0";
    timerDisplay.textContent = `Time Left: ${timeLeft}s`;

    clickButton.disabled = false;
    restartButton.disabled = true;
    gameMessage.textContent = "";

    gameInterval = setInterval(() => {
        timeLeft--;
        timerDisplay.textContent = `Time Left: ${timeLeft}s`;

        if (timeLeft === 0) {
            endGame();
        }
    }, 1000);
}

// Handle Clicks
function handleClick() {
    clickCount++;
    clickCountDisplay.textContent = `Clicks: ${clickCount}`;
}

// End Game
// End Game
function endGame() {
    clearInterval(gameInterval);
    clickButton.disabled = true;
    restartButton.disabled = false;

    const clicksPerSecond = parseFloat((clickCount / 5).toFixed(2));

    // Save score to backend
    fetch("Scripts/dbHelpers.php?action=saveScore", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            click_total: clickCount,
            clicks_per_sec: clicksPerSecond
        }),
    })
        .then((response) => response.json())
        .then((data) => {
            if (data.status === "success") {
                gameMessage.textContent = "Score saved successfully!";
                gameMessage.style.color = "green";
            } else {
                gameMessage.textContent = "Failed to save score: " + data.message;
                gameMessage.style.color = "red";
            }
        })
        .catch((error) => {
            console.error("Error saving score:", error);
        });
}


// Restart Game
function restartGame() {
    startGame();
}

// Event Listeners
clickButton.addEventListener("click", handleClick);
restartButton.addEventListener("click", restartGame);

// Initialize
window.onload = startGame;
