// Constants for game setup
const boardSize = 4; // 4x4 puzzle
let emptyX = 3, emptyY = 3; // Start with empty space at bottom-right
let moveCount = 0;
let timer = 0;
let interval = null;

// Background image options (full path URLs)
const backgrounds = [
  "https://codd.cs.gsu.edu/~jtran88/WP/PW/PW2/image/cinnamoroll.jpg",
  "https://codd.cs.gsu.edu/~jtran88/WP/PW/PW2/image/dog.jpg",
  "https://codd.cs.gsu.edu/~jtran88/WP/PW/PW2/image/egg.jpg",
  "https://codd.cs.gsu.edu/~jtran88/WP/PW/PW2/image/hellokitty.jpg",
  "https://codd.cs.gsu.edu/~jtran88/WP/PW/PW2/image/twin.jpg"
];

// Pick a random background on load
let backgroundImage = backgrounds[Math.floor(Math.random() * backgrounds.length)];

// DOM element references
const board = document.getElementById("board");
const moveCounter = document.getElementById("moveCount");
const timerDisplay = document.getElementById("timer");
const winBanner = document.getElementById("win-banner");
const bgSelect = document.getElementById("backgroundSelect");

let tiles = []; // 2D array of tiles

// Creates the puzzle tiles and places them on the board
function createTiles() {
  board.innerHTML = "";
  tiles = Array.from({ length: boardSize }, () => Array(boardSize).fill(null));
  let count = 1;

  for (let y = 0; y < boardSize; y++) {
    for (let x = 0; x < boardSize; x++) {
      if (x === 3 && y === 3) {
        tiles[y][x] = null; // Leave empty space at bottom-right
        continue;
      }

      const tile = document.createElement("div");
      tile.className = "tile";
      tile.textContent = count++;
      
      // Apply correct piece of background image
      tile.style.backgroundImage = `url(${backgroundImage})`;
      tile.style.backgroundPosition = `-${x * 100}px -${y * 100}px`;

      // Store logical position
      tile.dataset.x = x;
      tile.dataset.y = y;

      // Tile click and hover behavior
      tile.addEventListener("click", () => tryMoveTile(tile));
      tile.addEventListener("mouseover", () => hoverCheck(tile));
      tile.addEventListener("mouseout", () => tile.classList.remove("movablepiece"));

      board.appendChild(tile);
      tiles[y][x] = tile;
    }
  }

  updateBoard(); // Set initial tile positions visually
}

// Visually positions the tiles on screen based on their data-x/y
function updateBoard() {
  for (let y = 0; y < boardSize; y++) {
    for (let x = 0; x < boardSize; x++) {
      const tile = tiles[y][x];
      if (tile) {
        tile.style.left = `${x * 100}px`;
        tile.style.top = `${y * 100}px`;
        tile.dataset.x = x;
        tile.dataset.y = y;
      }
    }
  }
}

// Tries to move the clicked tile if it's adjacent to the empty space
function tryMoveTile(tile) {
  const x = parseInt(tile.dataset.x);
  const y = parseInt(tile.dataset.y);

  // Check if adjacent to empty
  if ((Math.abs(emptyX - x) === 1 && emptyY === y) ||
      (Math.abs(emptyY - y) === 1 && emptyX === x)) {

    tiles[emptyY][emptyX] = tile;
    tiles[y][x] = null;

    tile.dataset.x = emptyX;
    tile.dataset.y = emptyY;

    emptyX = x;
    emptyY = y;

    moveCount++;
    moveCounter.textContent = moveCount;

    updateBoard();
    checkWin();
  }
}

// Highlights a tile if it can be moved
function hoverCheck(tile) {
  const x = parseInt(tile.dataset.x);
  const y = parseInt(tile.dataset.y);

  if ((Math.abs(emptyX - x) === 1 && emptyY === y) ||
      (Math.abs(emptyY - y) === 1 && emptyX === x)) {
    tile.classList.add("movablepiece");
  }
}

// Shuffles the board into a solvable random state
function shuffle() {
  for (let i = 0; i < 300; i++) {
    const moves = [];
    if (emptyX > 0) moves.push([emptyX - 1, emptyY]);
    if (emptyX < boardSize - 1) moves.push([emptyX + 1, emptyY]);
    if (emptyY > 0) moves.push([emptyX, emptyY - 1]);
    if (emptyY < boardSize - 1) moves.push([emptyX, emptyY + 1]);

    const [x, y] = moves[Math.floor(Math.random() * moves.length)];
    moveTileSilently(x, y); // Internal tile move
  }

  moveCount = 0;
  moveCounter.textContent = moveCount;
  winBanner.style.display = "none";
  resetTimer(); // Start timer
  updateBoard();
}

// Moves a tile without animation or scoring (used for shuffling)
function moveTileSilently(x, y) {
  const tile = tiles[y][x];
  if (!tile) return;

  tiles[y][x] = null;
  tiles[emptyY][emptyX] = tile;

  tile.dataset.x = emptyX;
  tile.dataset.y = emptyY;

  tile.style.left = `${emptyX * 100}px`;
  tile.style.top = `${emptyY * 100}px`;

  emptyX = x;
  emptyY = y;
}

// Checks if puzzle is solved
function checkWin() {
  let count = 1;
  for (let y = 0; y < boardSize; y++) {
    for (let x = 0; x < boardSize; x++) {
      if (x === 3 && y === 3) continue;
      const tile = tiles[y][x];
      if (!tile || parseInt(tile.textContent) !== count++) return;
    }
  }

  clearInterval(interval); // Stop timer
  submitScore();           // Save score

  // Disable all interaction
  tiles.flat().forEach(tile => {
    if (tile) tile.onclick = null;
  });

  document.querySelectorAll('.tile').forEach(tile => {
    tile.style.pointerEvents = 'none';
  });

  document.getElementById("shuffleBtn").disabled = true;
  document.getElementById("leaderboardBtn").disabled = true;
  document.getElementById("resetBtn").disabled = true;

  // Show win screen and confetti
  document.body.classList.add("win");
  document.getElementById("win-banner").style.display = "block";
  document.getElementById("confetti").style.display = "block";
  document.getElementById("win-overlay").style.display = "block";
  document.getElementById("score-saved-msg").style.display = "block";
}

// Resets and starts timer
function resetTimer() {
  clearInterval(interval);
  timer = 0;
  timerDisplay.textContent = timer;
  interval = setInterval(() => {
    timer++;
    timerDisplay.textContent = timer;
  }, 1000);
}

// Submits score to PHP endpoint
function submitScore() {
  fetch("php/submit_score.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: `moves=${moveCount}&time=${timer}`,
  })
    .then((res) => res.text())
    .then(() => {
      console.log("Score submitted!");
    });
}

// Changes background when dropdown is used
bgSelect.addEventListener("change", (e) => {
  backgroundImage = e.target.value;

  for (let y = 0; y < boardSize; y++) {
    for (let x = 0; x < boardSize; x++) {
      const tile = tiles[y][x];
      if (tile) {
        tile.style.backgroundImage = `url(${backgroundImage})`;
      }
    }
  }
});

// Initializes game on page load
document.addEventListener("DOMContentLoaded", () => {
  createTiles();
  document.getElementById("shuffleBtn").addEventListener("click", shuffle);
});