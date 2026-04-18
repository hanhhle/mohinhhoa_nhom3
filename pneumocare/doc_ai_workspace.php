<?php
session_start();
require 'db.php';
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Doctor') { header("Location: login.php"); exit(); }
$doctorId = $_SESSION['user_id'];
$doctorName = $_SESSION['name'];
$doctorAvatar = (!empty($_SESSION['avatar']) && $_SESSION['avatar'] != 'default.png') ? $_SESSION['avatar'] : 'img/default.png';

$apptId = isset($_GET['appt_id']) ? $_GET['appt_id'] : null;
$patientInfo = null;
$msg = "";
$isValidAppointment = false;

// --- XỬ LÝ LƯU QUA AJAX (Để không reload trang) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    $aId = $_POST['appt_id'];
    $ai_result = $_POST['ai_result'];
    $confidence = $_POST['confidence'];
    $conclusion = trim($_POST['doctor_conclusion']);
    $finalNote = "Doctor Conclusion: " . $conclusion;
    
    try {
        $stmtSave = $pdo->prepare("UPDATE Appointments SET status = 'Completed', patient_notes = ?, ai_prediction_label = ?, ai_confidence_score = ? WHERE appointment_id = ? AND doctor_id = ?");
        $stmtSave->execute([$finalNote, $ai_result, $confidence, $aId, $doctorId]);
        echo json_encode(['status' => 'success']);
        exit();
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit();
    }
}

// LẤY THÔNG TIN CA KHÁM
if ($apptId) {
    try {
        $stmtAppt = $pdo->prepare("
            SELECT a.appointment_id, a.patient_id, a.patient_notes, a.status, u.full_name, u.avatar_url, pp.date_of_birth, pp.gender 
            FROM Appointments a
            JOIN Users u ON a.patient_id = u.user_id
            JOIN Patient_Profiles pp ON u.user_id = pp.patient_id
            WHERE a.appointment_id = ? AND a.doctor_id = ?
        ");
        $stmtAppt->execute([$apptId, $doctorId]);
        $appointmentInfo = $stmtAppt->fetch();

        if ($appointmentInfo) {
            $isValidAppointment = true;
            $patientInfo = $appointmentInfo;
            $symptomsStr = strtolower($appointmentInfo['patient_notes'] ?? '');
            $hasHeadache = strpos($symptomsStr, 'headache') !== false || strpos($symptomsStr, 'đau đầu') !== false;
            $hasChestPain = strpos($symptomsStr, 'chest pain') !== false || strpos($symptomsStr, 'đau ngực') !== false;
            $hasFever = strpos($symptomsStr, 'fever') !== false || strpos($symptomsStr, 'sốt') !== false;
            $hasShortness = strpos($symptomsStr, 'shortness') !== false || strpos($symptomsStr, 'khó thở') !== false;
        } else {
            $msg = "<div class='bg-red-50 text-red-600 p-6 rounded-2xl mb-6 border border-red-200 text-sm font-bold flex items-center justify-center gap-2 shadow-sm text-center'><i class='fa-solid fa-circle-exclamation text-lg'></i> Không tìm thấy lịch khám này!</div>";
        }
    } catch (PDOException $e) {}
} else {
    $msg = "<div class='bg-blue-50 text-blue-600 p-6 rounded-2xl mb-6 border border-blue-200 text-sm font-bold flex items-center justify-center gap-2 shadow-sm text-center'><i class='fa-solid fa-hand-pointer text-lg'></i> Vui lòng chọn một Bệnh nhân từ danh sách Appointments và bấm 'Start Exam'.</div>";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pneumo-Care | AI Diagnosis</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f4f7fa; color: #1f2937; }
        .sidebar-active { background-color: #eff6ff; color: #3b82f6; border-left: 4px solid #3b82f6; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .drop-zone { border: 2px dashed #cbd5e1; transition: all 0.3s ease; }
        .drop-zone.dragover { border-color: #3b82f6; background-color: #eff6ff; transform: scale(1.02); }
        @media print {
            body { background: white; }
            aside, header, #actionButtons, .drop-zone, #msgAlert, #changeImgBtn { display: none !important; }
            #printableArea { width: 100%; position: absolute; top: 0; left: 0; padding: 0; border: none; }
            .col-span-12 { width: 100% !important; }
            #resultPanel { opacity: 1 !important; pointer-events: auto !important; }
        }
        @keyframes scan { 0% { transform: translateY(0); opacity: 0.8; } 50% { transform: translateY(380px); opacity: 1; } 100% { transform: translateY(0); opacity: 0.8; } }
    </style>
</head>
<body class="flex h-screen overflow-hidden text-gray-800">

    <aside class="w-64 bg-white border-r border-gray-100 flex flex-col h-full shadow-sm">
        <div class="flex items-center gap-2 p-6 border-b">
            <i class="fa-solid fa-lungs text-3xl text-red-400"></i>
            <h1 class="text-xl font-semibold text-gray-700">Pneumo-<span class="text-blue-500">Care</span></h1>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-1">
            <a href="doc_dashboard.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors"><i class="fa-solid fa-gauge-high w-5"></i><span>Dashboard</span></a>
            <a href="doc_patient_list.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors"><i class="fa-solid fa-users w-5"></i><span>Patient</span></a>
            <a href="doc_appointments.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors"><i class="fa-solid fa-calendar-check w-5"></i><span>Appointments</span></a>
            <a href="doc_ai_workspace.php" class="sidebar-active flex items-center gap-4 px-4 py-3 rounded-lg font-medium"><i class="fa-solid fa-brain w-5"></i><span>Diagnosis</span></a>
            <a href="doc_messages.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors"><i class="fa-solid fa-comment-dots w-5"></i><span>Messages</span></a>
        </nav>
        <div class="p-6 border-t mt-auto"><a href="logout.php" class="flex items-center gap-4 text-gray-500 hover:text-red-500 transition-colors font-medium"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a></div>
    </aside>

    <main class="flex-1 flex flex-col overflow-hidden bg-[#f4f7fa]">
        <div class="px-10 pt-8 pb-6 flex-shrink-0">
            <header class="h-[72px] bg-white border border-gray-100 rounded-2xl shadow-sm flex items-center justify-between px-6">
                <h2 class="text-2xl font-bold text-[#003366]">AI Diagnosis Workspace</h2>
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-3">
                        <div class="text-right hidden sm:block"><p class="text-sm font-semibold text-gray-800" style="line-height: 1.2;"><?php echo htmlspecialchars($doctorName); ?></p><p class="text-xs text-gray-500 font-medium">Doctor</p></div>
                        <img src="<?php echo $doctorAvatar; ?>" class="w-10 h-10 rounded-full border border-gray-200 shadow-sm object-cover">
                    </div>
                </div>
            </header>
        </div>

        <div class="flex-1 px-10 pb-10 overflow-y-auto">
            <div id="msgAlert"><?php echo $msg; ?></div>

            <?php if($isValidAppointment): ?>
            <div class="grid grid-cols-12 gap-8 h-full" id="printableArea">
                <div class="col-span-12 lg:col-span-7 bg-white rounded-2xl shadow-sm border border-gray-100 p-8 flex flex-col h-full">
                    <h3 class="font-bold text-gray-900 text-lg mb-6">Chest X-ray Image</h3>
                    
                    <div id="dropZone" class="drop-zone bg-gray-50 rounded-2xl h-[400px] flex flex-col items-center justify-center cursor-pointer mb-6 group">
                        <input type="file" id="xrayInput" accept="image/*" class="hidden">
                        <div class="w-20 h-20 bg-white rounded-full shadow-sm flex items-center justify-center mb-4 group-hover:scale-110 transition-transform"><i class="fa-solid fa-cloud-arrow-up text-3xl text-blue-500"></i></div>
                        <p class="text-gray-800 font-semibold text-lg">Kéo thả ảnh X-quang vào đây</p>
                        <p class="text-sm text-gray-500 mt-2 mb-6">Hỗ trợ định dạng: JPG, PNG</p>
                        <button onclick="document.getElementById('xrayInput').click()" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-8 py-3 rounded-xl shadow-md transition-colors">Chọn ảnh từ máy</button>
                    </div>

                    <div id="previewArea" class="hidden mb-6 flex-1 flex flex-col items-center">
                        <div class="relative w-full h-[400px] bg-black rounded-2xl overflow-hidden shadow-inner border-4 border-gray-200">
                            <img id="xrayPreview" class="w-full h-full object-contain" alt="X-ray">
                            <div id="scanOverlay" class="hidden absolute inset-0 bg-blue-500/20"><div class="w-full h-2 bg-blue-400 shadow-[0_0_15px_#3b82f6] animate-[scan_2s_ease-in-out_infinite]"></div></div>
                        </div>
                        <button id="changeImgBtn" onclick="clearImage()" class="mt-4 text-red-500 text-sm font-semibold hover:underline"><i class="fa-solid fa-rotate-left mr-1"></i> Thay đổi ảnh khác</button>
                    </div>

                    <div class="mt-auto flex justify-center gap-4" id="actionButtons">
                        <button onclick="runAIPrediction()" id="aiButton" disabled class="bg-gray-300 text-gray-500 font-bold px-12 py-4 rounded-xl flex items-center gap-3 transition-all uppercase tracking-wide text-sm cursor-not-allowed">
                            <i class="fa-solid fa-brain text-xl"></i><span>Run AI Prediction</span>
                        </button>

                        <button onclick="saveDiagnosis()" id="saveButton" class="hidden bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-10 py-4 rounded-xl flex items-center gap-3 transition-all shadow-md uppercase tracking-wide text-sm">
                            <i class="fa-solid fa-floppy-disk text-xl"></i><span>Save Result</span>
                        </button>

                        <button onclick="window.print()" id="printButton" class="hidden bg-white border-2 border-gray-200 text-gray-600 font-bold px-8 py-4 rounded-xl flex items-center gap-3 hover:bg-gray-50 transition-all shadow-sm uppercase text-sm">
                            <i class="fa-solid fa-print text-xl"></i><span>Print</span>
                        </button>

                        <a href="doc_medical_record.php?patient_id=<?php echo $patientInfo['patient_id']; ?>" id="continueButton" class="hidden bg-blue-600 hover:bg-blue-700 text-white font-bold px-10 py-4 rounded-xl flex items-center gap-3 transition-all shadow-lg uppercase tracking-wide text-sm">
                            <span>Continue on Medical Record</span><i class="fa-solid fa-arrow-right text-xl"></i>
                        </a>
                    </div>
                    <p id="apiErrorMsg" class="text-red-500 text-sm text-center font-semibold mt-3 hidden"></p>
                </div>

                <div class="col-span-12 lg:col-span-5 bg-white rounded-2xl shadow-sm border border-gray-100 flex flex-col relative overflow-hidden transition-all duration-500" id="resultPanel" style="opacity: 0.5; pointer-events: none;">
                    <div class="p-8 flex flex-col h-full">
                        <h3 class="font-bold text-gray-900 text-lg mb-6">AI Results and Diagnosis</h3>
                        <div id="aiResultBox" class="bg-gray-50 border border-gray-200 rounded-2xl p-6 mb-8 text-center transition-colors">
                            <p class="text-gray-500 font-semibold uppercase tracking-widest text-xs mb-2">Pneumonia Risk</p>
                            <p id="aiLabel" class="text-3xl font-extrabold text-gray-400 mb-2">Waiting for Image...</p>
                            <p class="text-sm text-gray-500 font-medium">Confidence level: <span id="aiConf" class="font-bold">--%</span></p>
                        </div>

                        <div class="grid grid-cols-2 gap-8 mb-8 border-b border-gray-100 pb-8">
                            <div>
                                <h4 class="font-bold text-gray-800 text-sm uppercase tracking-wide mb-4">Patient Symptoms</h4>
                                <div class="space-y-3 text-sm text-gray-700 font-medium">
                                    <label class="flex items-center gap-3"><input type="checkbox" <?php echo !empty($hasHeadache) ? 'checked' : ''; ?> class="w-4 h-4 accent-blue-600 rounded"> Headache</label>
                                    <label class="flex items-center gap-3"><input type="checkbox" <?php echo !empty($hasChestPain) ? 'checked' : ''; ?> class="w-4 h-4 accent-blue-600 rounded"> Chest pain</label>
                                    <label class="flex items-center gap-3"><input type="checkbox" <?php echo !empty($hasFever) ? 'checked' : ''; ?> class="w-4 h-4 accent-blue-600 rounded"> Fever</label>
                                    <label class="flex items-center gap-3"><input type="checkbox" <?php echo !empty($hasShortness) ? 'checked' : ''; ?> class="w-4 h-4 accent-blue-600 rounded"> Shortness of breath</label>
                                </div>
                            </div>
                            <div class="text-center bg-gray-50 rounded-2xl p-4 border border-gray-100">
                                <img src="<?php echo $patientInfo['avatar_url'] ?: 'img/default.png'; ?>" class="w-20 h-20 mx-auto rounded-full object-cover shadow-sm mb-3">
                                <p class="font-bold text-gray-800"><?php echo htmlspecialchars($patientInfo['full_name']); ?></p>
                                <p class="text-xs text-gray-500 font-medium mt-1"><?php echo calculateAge($patientInfo['date_of_birth']); ?> years old • <?php echo $patientInfo['gender']; ?></p>
                            </div>
                        </div>

                        <div class="mb-8 flex-1">
                            <h4 class="font-bold text-gray-800 text-sm uppercase tracking-wide mb-3">Doctor's Conclusion</h4>
                            <textarea id="docConclusion" class="w-full h-32 bg-blue-50/50 border border-blue-100 rounded-2xl p-5 text-sm text-gray-700 font-medium focus:outline-none focus:border-blue-400 focus:bg-white transition-colors resize-none placeholder-gray-400" placeholder="Write your clinical conclusion here..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

<script>
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('xrayInput');
    const previewArea = document.getElementById('previewArea');
    const xrayPreview = document.getElementById('xrayPreview');
    const aiButton = document.getElementById('aiButton');
    const saveButton = document.getElementById('saveButton');
    const printButton = document.getElementById('printButton');
    const continueButton = document.getElementById('continueButton');
    const resultPanel = document.getElementById('resultPanel');
    const scanOverlay = document.getElementById('scanOverlay');
    const apiErrorMsg = document.getElementById('apiErrorMsg');
    
    let selectedFile = null;
    let ai_result_val = "";
    let confidence_val = "";

    // LOGIC KÉO THẢ
    if (dropZone) {
        dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('dragover'); });
        dropZone.addEventListener('dragleave', () => { dropZone.classList.remove('dragover'); });
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault(); dropZone.classList.remove('dragover');
            if (e.dataTransfer.files.length) handleFile(e.dataTransfer.files[0]);
        });
        fileInput.addEventListener('change', (e) => { if (e.target.files.length) handleFile(e.target.files[0]); });
    }

    function handleFile(file) {
        if(!file.type.match('image.*')) { alert("Lỗi: Chỉ tải file ảnh JPG/PNG."); return; }
        selectedFile = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            xrayPreview.src = e.target.result;
            previewArea.classList.remove('hidden');
            dropZone.classList.add('hidden');
            aiButton.disabled = false;
            aiButton.className = "bg-blue-600 hover:bg-blue-700 text-white font-bold px-12 py-4 rounded-xl flex items-center gap-3 transition-all shadow-md uppercase tracking-wide text-sm cursor-pointer";
        };
        reader.readAsDataURL(file);
    }

    function clearImage() {
        fileInput.value = ""; selectedFile = null;
        previewArea.classList.add('hidden'); dropZone.classList.remove('hidden');
        aiButton.disabled = true; aiButton.classList.remove('hidden');
        saveButton.classList.add('hidden'); printButton.classList.add('hidden'); continueButton.classList.add('hidden');
        aiButton.className = "bg-gray-300 text-gray-500 font-bold px-12 py-4 rounded-xl flex items-center gap-3 transition-all uppercase tracking-wide text-sm cursor-not-allowed";
        resultPanel.style.opacity = "0.5"; resultPanel.style.pointerEvents = "none";
    }

    // --- BƯỚC 1: CHẠY AI ---
    async function runAIPrediction() {
        if(!selectedFile) return;
        scanOverlay.classList.remove('hidden');
        aiButton.innerHTML = `<i class="fa-solid fa-spinner fa-spin text-xl"></i><span>Analyzing...</span>`;
        aiButton.disabled = true;

        const formData = new FormData();
        formData.append('file', selectedFile);

        try {
            const response = await fetch('http://127.0.0.1:5000/predict', { method: 'POST', body: formData });
            const data = await response.json();
            
            scanOverlay.classList.add('hidden');
            aiButton.classList.add('hidden'); // Ẩn nút AI
            saveButton.classList.remove('hidden'); // Hiện nút Save
            printButton.classList.remove('hidden'); // Hiện nút Print
            
            resultPanel.style.opacity = "1";
            resultPanel.style.pointerEvents = "auto";

            const aiBox = document.getElementById('aiResultBox');
            const aiLabel = document.getElementById('aiLabel');
            const aiConf = document.getElementById('aiConf');
            const docConclusion = document.getElementById('docConclusion');

            ai_result_val = data.result;
            confidence_val = data.confidence;
            aiConf.textContent = data.confidence + "%";

            if (data.result === "Positive") {
                aiBox.className = "bg-red-50 border border-red-200 rounded-2xl p-6 mb-8 text-center";
                aiLabel.className = "text-3xl font-extrabold text-red-600 mb-2";
                aiLabel.textContent = "Positive (High Risk)";
                docConclusion.value = "AI detected signs of pneumonia. Recommended immediate clinical review.";
            } else {
                aiBox.className = "bg-green-50 border border-green-200 rounded-2xl p-6 mb-8 text-center";
                aiLabel.className = "text-3xl font-extrabold text-green-600 mb-2";
                aiLabel.textContent = "Negative (Normal)";
                docConclusion.value = "Normal lung scan. No signs of pneumonia detected by AI.";
            }
        } catch (error) {
            alert("Lỗi kết nối AI Server!");
            aiButton.innerHTML = `<i class="fa-solid fa-brain text-xl"></i><span>Retry Prediction</span>`;
            aiButton.disabled = false;
        }
    }

    // --- BƯỚC 2: LƯU KẾT QUẢ (AJAX) ---
    async function saveDiagnosis() {
        saveButton.disabled = true;
        saveButton.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i><span>Saving...</span>`;

        const formData = new FormData();
        formData.append('ajax_save', '1');
        formData.append('appt_id', '<?php echo $apptId; ?>');
        formData.append('ai_result', ai_result_val);
        formData.append('confidence', confidence_val);
        formData.append('doctor_conclusion', document.getElementById('docConclusion').value);

        try {
            const response = await fetch(window.location.href, { method: 'POST', body: formData });
            const data = await response.json();
            
            if(data.status === 'success') {
                saveButton.classList.add('hidden'); // Ẩn nút Save
                continueButton.classList.remove('hidden'); // Hiện nút Tiếp tục
                alert("The diagnosis results have been saved to the patient's medical record!");
            } else {
                alert("Lỗi lưu dữ liệu: " + data.message);
                saveButton.disabled = false;
                saveButton.innerHTML = `<i class="fa-solid fa-floppy-disk text-xl"></i><span>Save Result</span>`;
            }
        } catch (error) {
            alert("Lỗi kết nối Server để lưu!");
            saveButton.disabled = false;
        }
    }
</script>
</body>
</html>