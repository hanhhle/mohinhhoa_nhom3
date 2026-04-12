-- 1. QUẢN LÝ TÀI KHOẢN
CREATE TABLE Users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('Admin', 'Doctor', 'Patient') NOT NULL,
    avatar_url TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. HỒ SƠ CHI TIẾT
CREATE TABLE Patient_Profiles (
    patient_id INT PRIMARY KEY,
    date_of_birth DATE NOT NULL,
    gender ENUM('Male', 'Female', 'Other'),
    blood_group VARCHAR(5),
    phone_number VARCHAR(20),
    address TEXT,
    identity_card_number VARCHAR(20) UNIQUE,
    health_insurance_code VARCHAR(50),
    FOREIGN KEY (patient_id) REFERENCES Users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Doctor_Profiles (
    doctor_id INT PRIMARY KEY,
    speciality VARCHAR(100),
    consultation_fee DECIMAL(10,2),
    clinic_address TEXT,
    room_details TEXT,
    undergraduate_edu TEXT,
    medical_edu TEXT,
    training TEXT,
    affiliations TEXT,
    bio TEXT,
    rating DECIMAL(2,1) DEFAULT 5.0,
    FOREIGN KEY (doctor_id) REFERENCES Users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. TIỀN SỬ BỆNH
CREATE TABLE Medical_History (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    condition_name TEXT NOT NULL, 
    type ENUM('Disease', 'Surgery'), 
    date_recorded DATE,
    FOREIGN KEY (patient_id) REFERENCES Patient_Profiles(patient_id)
) ENGINE=InnoDB;

-- 4. APPOINTMENT (Đã sửa phương thức thanh toán)
CREATE TABLE Appointments (
    appointment_id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_code VARCHAR(20) UNIQUE,
    numerical_order INT,
    patient_id INT,
    doctor_id INT,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    patient_symptoms_note TEXT,
    status ENUM('Scheduled', 'Completed', 'Cancelled') DEFAULT 'Scheduled',
    fee_status ENUM('Paid', 'Unpaid') DEFAULT 'Unpaid',
    fee_amount DECIMAL(10,2),
    -- Chỉ cho phép Tiền mặt hoặc Chuyển khoản
    payment_method ENUM('Tiền mặt', 'Chuyển khoản'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES Patient_Profiles(patient_id),
    FOREIGN KEY (doctor_id) REFERENCES Doctor_Profiles(doctor_id)
) ENGINE=InnoDB;

-- 5. CHẨN ĐOÁN AI & KẾT LUẬN LÂM SÀNG (Đã sửa JSONB thành JSON)
CREATE TABLE AI_Diagnosis_Sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT,
    xray_image_url TEXT NOT NULL, 
    ai_result_label VARCHAR(50), 
    ai_confidence_level DECIMAL(5,2), 
    raw_model_output JSON, -- Sửa thành JSON
    patient_symptoms JSON, -- Sửa thành JSON
    doctor_final_conclusion TEXT,
    is_printed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES Appointments(appointment_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. PHÁC ĐỒ ĐIỀU TRỊ & THAM VẤN
CREATE TABLE Treatment_Plans (
    plan_id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT,
    diagnose_note TEXT, 
    clinical_notes TEXT,
    treatment_steps TEXT, 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES Appointments(appointment_id)
) ENGINE=InnoDB;

CREATE TABLE Expert_Comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT,
    doctor_id INT, 
    comment_content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES Appointments(appointment_id),
    FOREIGN KEY (doctor_id) REFERENCES Doctor_Profiles(doctor_id)
) ENGINE=InnoDB;

-- 7. TIN NHẮN
CREATE TABLE Messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT,
    receiver_id INT,
    message_content TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES Users(user_id),
    FOREIGN KEY (receiver_id) REFERENCES Users(user_id)
) ENGINE=InnoDB;