<?php
// Load the JSON data
$jsonData = file_get_contents('../backend/Api/members.json');
$membersData = json_decode($jsonData, true);

// Get the selected year (default to 2025)
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : 2025;

// Find the selected year data
$selectedYearData = null;
foreach ($membersData['years'] as $yearData) {
    if ($yearData['year'] == $selectedYear) {
        $selectedYearData = $yearData;
        break;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Previous Panel Members | BRACU Cultural Club</title>
  <link href="css/bootstrap.min.css" rel="stylesheet">
  <link href="css/bootstrap-icons.css" rel="stylesheet">
  <link href="css/templatemo-festava-live.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@200;300;400;500;600;700;800;900;1000&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Nunito', sans-serif;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      background-image: url('https://github.com/ecemgo/mini-samples-great-tricks/assets/13468728/aa462558-0106-4268-9864-d34a4f35531f');
      background-repeat: no-repeat;
      background-size: cover;
      background-position: center;
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
      max-width: 100vw;
      position: relative;
    }
    .animated-gradient {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      z-index: -2;
      pointer-events: none;
      opacity: 0.55;
      background: linear-gradient(120deg,
        #f8e7b9 0%,      /* soft gold */
        #b6d0e2 10%,     /* light blue */
        #c3aed6 20%,     /* lavender */
        #a084ca 30%,     /* light purple */
        #7c3aed 40%,     /* vibrant purple */
        #5e60ce 50%,     /* blue purple */
        #232946 60%,     /* dark blue */
        #3a0ca3 70%,     /* deep purple */
        #181c2f 80%,     /* deep dark */
        #0a0a23 90%,     /* almost black */
        #232946 100%,    /* dark blue */
        #7c3aed 90%,     /* vibrant purple */
        #c3aed6 80%,     /* lavender */
        #b6d0e2 70%,     /* light blue */
        #f8e7b9 60%      /* soft gold */
      );
      background-size: 400% 400%;
      animation: gradientMove 22s ease-in-out infinite;
      mix-blend-mode: lighten;
    }
    .bg-darken {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      z-index: -1;
      background: rgba(0,0,0,0.45);
      pointer-events: none;
    }
    @keyframes gradientMove {
      0% { background-position: 0% 0%; }
      20% { background-position: 25% 50%; }
      40% { background-position: 50% 100%; }
      60% { background-position: 75% 50%; }
      80% { background-position: 100% 0%; }
      100% { background-position: 0% 0%; }
    }
    .container-panel {
      max-width: 1200px;
      margin: 0 auto;
      padding: 32px 12px 64px 12px;
      position: relative;
      z-index: 1;
    }
    .back-btn {
      margin: 32px 0 24px 0;
      display: inline-block;
      background: #ffd700;
      color: #0a1931;
      font-weight: 700;
      border-radius: 2em;
      padding: 10px 32px;
      text-decoration: none;
      font-size: 1.1em;
      box-shadow: 0 2px 12px #0003;
      transition: background 0.2s, color 0.2s;
    }
    .back-btn:hover {
      background: #fff;
      color: #0a1931;
      text-decoration: none;
    }
    h1, h2, h3, h4 {
      color: #ffd700;
      font-weight: 700;
      text-align: center;
      margin-bottom: 1.5rem;
    }
    .year-section {
      margin-bottom: 48px;
    }
    .panel-row {
      display: flex;
      flex-wrap: wrap;
      gap: 18px;
      justify-content: center;
      margin-bottom: 24px;
    }
    .panel-card {
      background: #fff;
      color: #0a1931;
      border-radius: 16px;
      box-shadow: 0 2px 12px #0002, 0 0 0 2px #ffd70033;
      padding: 18px 8px 10px 8px;
      min-width: 140px;
      max-width: 180px;
      margin-bottom: 12px;
      border: 2px solid #ffd700;
      text-align: center;
      transition: transform 0.18s, box-shadow 0.18s;
    }
    .panel-card:hover {
      transform: translateY(-4px) scale(1.03);
      box-shadow: 0 8px 32px #ffd70033, 0 0 0 4px #ffd70055;
      z-index: 2;
    }
    .panel-card img {
      width: 120px;
      height: 120px;
      aspect-ratio: 1/1;
      object-fit: cover;
      border-radius: 12px;
      border: 2.5px solid #ffd700;
      box-shadow: 0 2px 12px #0004;
      margin-bottom: 8px;
      background: #fff;
      transition: transform 0.25s cubic-bezier(.4,2,.6,1), box-shadow 0.25s;
      animation: floatImage 3.5s ease-in-out infinite alternate;
      display: block;
      margin-left: auto;
      margin-right: auto;
    }
    .panel-card:hover img {
      transform: scale(1.08) rotate(-2deg);
      box-shadow: 0 8px 32px #ffd70055, 0 0 0 4px #ffd70033;
      z-index: 2;
    }
    @keyframes floatImage {
      0%   { transform: translateY(0px) scale(1);}
      50%  { transform: translateY(-10px) scale(1.03);}
      100% { transform: translateY(0px) scale(1);}
    }
    .panel-card .name {
      font-weight: 600;
      font-size: 1.08em;
      color: #0a1931;
      margin-bottom: 2px;
    }
    .panel-card .position {
      font-size: 0.98em;
      color: #1a2639;
      opacity: 0.85;
    }
    .year-btn {
      font-weight: 700;
      border-radius: 2em;
      padding: 10px 20px;
      font-size: 1.1em;
      box-shadow: 0 2px 12px #0003;
      transition: background 0.2s, color 0.2s;
    }
    .year-btn:hover {
      background: #fff;
      color: #0a1931;
      text-decoration: none;
    }
    .year-btn.active {
      background: #ffd700;
      color: #0a1931;
    }
    @media (max-width: 768px) {
      .panel-row { gap: 10px; }
      .panel-card { min-width: 110px; max-width: 140px; padding: 10px 2px 6px 2px; }
      .panel-card img {
        width: 80px;
        height: 80px;
      }
    }
  </style>
</head>
<body>
  <div class="animated-gradient"></div>
  <div class="bg-darken"></div>
  <div class="container-panel">
    <a href="index.php" class="back-btn">&larr; Back to Home</a>
    <h1>Previous Panel Members & Secretaries (2022–2025)</h1>

    <!-- Year Selection Bar -->
    <div class="d-flex justify-content-center mb-4">
      <div class="btn-group" role="group" aria-label="Year selection" id="yearSelectBar">
        <?php foreach ($membersData['years'] as $yearData): ?>
          <a href="?year=<?php echo $yearData['year']; ?>" 
             class="btn <?php echo ($yearData['year'] == $selectedYear) ? 'btn-warning active' : 'btn-outline-light'; ?> year-btn">
            <?php echo $yearData['year']; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ($selectedYearData): ?>
    <!-- Selected Year Section -->
    <div class="year-section">
      <h2><?php echo $selectedYearData['year']; ?></h2>
      
      <!-- Panel Members -->
      <h4>Panel Members</h4>
      <div class="panel-row">
        <?php foreach ($selectedYearData['panel_members'] as $member): ?>
          <div class="panel-card">
            <img src="<?php echo htmlspecialchars($member['image']); ?>" 
                 alt="<?php echo htmlspecialchars($member['name']); ?>"
                 onerror="this.src='images/placeholder.png'">
            <div class="name"><?php echo htmlspecialchars($member['name']); ?></div>
            <?php if (!empty($member['position']) && $member['position'] !== 'Panel Member'): ?>
              <div class="position"><?php echo htmlspecialchars($member['position']); ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <!-- Secretaries -->
      <h4>Secretaries</h4>
      <div class="panel-row">
        <?php foreach ($selectedYearData['secretaries'] as $secretary): ?>
          <div class="panel-card">
            <img src="<?php echo htmlspecialchars($secretary['image']); ?>" 
                 alt="<?php echo htmlspecialchars($secretary['name']); ?>"
                 onerror="this.src='images/placeholder.png'">
            <div class="name"><?php echo htmlspecialchars($secretary['name']); ?></div>
            <?php if (!empty($secretary['position']) && $secretary['position'] !== 'Secretary'): ?>
              <div class="position"><?php echo htmlspecialchars($secretary['position']); ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php else: ?>
    <div class="text-center text-white">
      <h3>No data available for the selected year.</h3>
    </div>
    <?php endif; ?>
  </div>

<script>
// Year selection logic
document.addEventListener('DOMContentLoaded', function() {
  const yearBtns = document.querySelectorAll('.year-btn');
  
  yearBtns.forEach(btn => {
    btn.addEventListener('click', function(e) {
      // Remove active class from all buttons
      yearBtns.forEach(b => {
        b.classList.remove('active', 'btn-warning');
        b.classList.add('btn-outline-light');
      });
      
      // Add active class to clicked button
      this.classList.add('active', 'btn-warning');
      this.classList.remove('btn-outline-light');
      
      // Smooth scroll to top
      window.scrollTo({top: 0, behavior: 'smooth'});
    });
  });
});
</script>
</body>
</html> 