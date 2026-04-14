<?php
// ==========================================
// TÊN FILE: doc_ai_workspace.php
// CHỨC NĂNG: Giao diện AI Diagnosis kéo thả ảnh
// ==========================================
session_start();
require 'db.php';
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Doctor') { header("Location: login.php"); exit(); }
$doctorName = $_SESSION['name'];
$doctorAvatar = (!empty($_SESSION['avatar']) && $_SESSION['avatar'] != 'default.png') ? $_SESSION['avatar'] : 'img/default.png';

// Lấy thông tin bệnh nhân đang được chọn (nếu có)
$patientInfo = null;
if (isset($_GET['patient_id'])) {
    $stmt = $pdo->prepare("SELECT u.full_name, u.avatar_url, pp.date_of_birth, pp.gender FROM Users u JOIN Patient_Profiles pp ON u.user_id = pp.patient_id WHERE u.user_id = ?");
    $stmt->execute([$_GET['patient_id']]);
    $patientInfo = $stmt->fetch();
}

function calculateAge($birthDate) { 
    if(!$birthDate) return "N/A";
    return date_diff(date_create($birthDate), date_create('today'))->y; 
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Pneumo-Care | AI Diagnosis Workspace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .sidebar-active { background-color: #eff6ff; color: #2563eb; border-left: 4px solid #2563eb; }
        .drop-zone { border: 2px dashed #94a3b8; }
        .drop-zone.dragover { border-color: #3b82f6; background-color: #eff6ff; }
    </style>
</head>
<body class="flex h-screen overflow-hidden text-gray-800">
    <aside class="w-64 bg-white border-r border-gray-100 flex flex-col h-full shadow-sm">
        <div class="flex items-center gap-2 p-6 border-b"><i class="fa-solid fa-lungs text-3xl text-red-400"></i><h1 class="text-xl font-semibold text-gray-700">Pneumo-<span class="text-blue-500">Care</span></h1></div>
            <nav class="flex-1 px-4 py-6 space-y-1">
                <a href="doc_dashboard.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                    <i class="fa-solid fa-gauge-high w-5"></i>
                    <span>Dashboard</span>
                </a>
                <a href="doc_patient_list.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                    <i class="fa-solid fa-users w-5"></i>
                    <span>Patient</span>
                </a>
                <a href="doc_appointments.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                    <i class="fa-solid fa-calendar-check w-5"></i>
                    <span>Appointments</span>
                </a>
                <a href="doc_ai_workspace.php" class="sidebar-active flex items-center gap-4 px-4 py-3 rounded-lg font-medium">
                    <i class="fa-solid fa-brain w-5"></i>
                    <span>AI Diagnosis</span>
                </a>
                <a href="#" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                    <i class="fa-solid fa-comment-dots w-5"></i>
                    <span>Messages</span>
                </a>
            </nav>
        <div class="p-6 border-t mt-auto"><a href="logout.php" class="flex items-center gap-4 text-gray-500 hover:text-red-500 font-medium"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a></div>
    </aside>

    <main class="flex-1 overflow-y-auto bg-gray-50">
        <div class="p-8">
            <header class="flex justify-between items-center mb-8">
                <h2 class="text-2xl font-semibold text-gray-700">AI Diagnosis Workspace</h2>
                <div class="flex items-center gap-6">
                    <div class="relative cursor-pointer"><i class="fa-solid fa-bell text-xl text-gray-400"></i><span class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-red-500 rounded-full"></span></div>
                    <div class="flex items-center gap-3">
                        <img src="<?php echo $doctorAvatar; ?>" class="w-10 h-10 rounded-full border-2 border-white shadow object-cover" alt="">
                        <div><p class="font-semibold text-sm"><?php echo htmlspecialchars($doctorName); ?></p><p class="text-xs text-gray-500">Doctor</p></div>
                    </div>
                </div>
            </header>

            <div class="grid grid-cols-12 gap-6">
                <div class="col-span-12 lg:col-span-7 bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h3 class="font-semibold mb-4">Chest X-ray Image</h3>
                    
                    <div id="dropZone" class="drop-zone border-2 border-dashed rounded-2xl h-96 flex flex-col items-center justify-center cursor-pointer transition-colors">
                        <input type="file" id="xrayInput" accept="image/*" class="hidden">
                        <i class="fa-solid fa-cloud-arrow-up text-5xl text-gray-400 mb-4"></i>
                        <p class="text-gray-600 font-medium">Kéo thả ảnh X-quang vào đây</p>
                        <p class="text-sm text-gray-500 mt-1">hoặc click để chọn file</p>
                        <button onclick="document.getElementById('xrayInput').click()" class="mt-6 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Chọn ảnh X-ray</button>
                    </div>

                    <div id="previewArea" class="hidden mt-4">
                        <img id="xrayPreview" class="w-full rounded-xl border shadow-sm" alt="X-ray">
                    </div>

                    <div class="mt-6 flex justify-center">
                        <button onclick="runAI()" id="aiButton" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-10 py-3.5 rounded-xl flex items-center gap-3 transition">
                            <i class="fa-solid fa-brain"></i><span>Running AI predictions</span>
                        </button>
                    </div>
                </div>

                <div id="resultPanel" class="col-span-12 lg:col-span-5 hidden">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-6">
                        <h3 class="font-semibold">AI results and Diagnosis</h3>
                        
                        <div class="bg-red-50 border border-red-200 rounded-xl p-5">
                            <p class="text-red-700 font-medium">Pneumonia Risk</p>
                            <p class="text-2xl font-bold text-red-600">Positive (High)</p>
                            <p class="text-sm text-gray-600 mt-1">Confidence level: <span class="font-semibold text-red-600">94.5%</span></p>
                        </div>

                        <?php if($patientInfo): ?>
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <h4 class="font-medium mb-3">Patient Symptoms</h4>
                                <div class="space-y-2 text-sm text-gray-600">
                                    <div class="flex items-center gap-2"><input type="checkbox" checked class="accent-blue-600"> Headache</div>
                                    <div class="flex items-center gap-2"><input type="checkbox" checked class="accent-blue-600"> Chest pain</div>
                                    <div class="flex items-center gap-2"><input type="checkbox" checked class="accent-blue-600"> Fever</div>
                                    <div class="flex items-center gap-2"><input type="checkbox"> Shortness of breath</div>
                                </div>
                            </div>
                            <div class="text-center">
                                <img src="<?php echo $patientInfo['avatar_url'] ?: 'img/default.png'; ?>" class="w-24 h-24 mx-auto rounded-2xl object-cover" alt="">
                                <p class="font-medium mt-3"><?php echo htmlspecialchars($patientInfo['full_name']); ?></p>
                                <p class="text-sm text-gray-500"><?php echo calculateAge($patientInfo['date_of_birth']); ?> tuổi • <?php echo $patientInfo['gender']; ?></p>
                            </div>
                        </div>
                        <?php else: ?>
                            <div class="p-4 bg-gray-50 rounded-xl text-center text-sm text-gray-500 italic">Chưa chọn bệnh nhân cụ thể.</div>
                        <?php endif; ?>

                        <div>
                            <h4 class="font-semibold mb-2">Doctor's Conclusion</h4>
                            <textarea class="w-full bg-blue-50 border border-blue-100 rounded-xl p-4 text-sm focus:outline-none focus:border-blue-300" rows="3">The AI result are consistent with the clinical symptoms. Hospitalization for monitoring and treatment is recommendation.</textarea>
                        </div>

                        <div class="flex gap-4 pt-4">
                            <button class="flex-1 bg-blue-600 text-white font-medium py-4 rounded-xl hover:bg-blue-700">Save & Complete</button>
                            <button class="flex-1 border border-gray-300 font-medium py-4 rounded-xl hover:bg-gray-50">Print Result</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('xrayInput');
        const previewArea = document.getElementById('previewArea');
        const xrayPreview = document.getElementById('xrayPreview');
        const resultPanel = document.getElementById('resultPanel');

        dropZone.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) handleFile(file);
        });

        function handleFile(file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                xrayPreview.src = e.target.result;
                previewArea.classList.remove('hidden');
                dropZone.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        }

        function runAI() {
            const btn = document.getElementById('aiButton');
            btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i><span>Đang phân tích...</span>`;
            btn.disabled = true;

            setTimeout(() => {
                resultPanel.classList.remove('hidden');
                btn.innerHTML = `<i class="fa-solid fa-check"></i><span>Hoàn thành phân tích</span>`;
                alert("✅ AI đã phân tích xong!\nKết quả: Pneumonia - Positive (High) - 94.5%");
            }, 1800);
        }
    </script>
</body>
</html>