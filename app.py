from flask import Flask, request, jsonify
from flask_cors import CORS
import numpy as np
from tensorflow.keras.models import load_model
from PIL import Image
import io

app = Flask(__name__)
CORS(app) # Cho phép web gọi API

# Load model của bạn
MODEL_PATH = 'improved_saved_ai.h5'
try:
    model = load_model(MODEL_PATH)
    print(f"✅ Đã load thành công model: {MODEL_PATH}")
except Exception as e:
    print(f"❌ Lỗi load model: {e}")

def preprocess_image(image_bytes):
    # CHÚ Ý: Chỉnh size ảnh 224x224 cho khớp với model AlexNet/ViT của bạn
    img = Image.open(io.BytesIO(image_bytes)).convert('RGB')
    img = img.resize((224, 224)) 
    img_array = np.array(img) / 255.0
    return np.expand_dims(img_array, axis=0)

@app.route('/predict', methods=['POST'])
def predict():
    if 'file' not in request.files:
        return jsonify({'error': 'No file'}), 400
    
    file = request.files['file'].read()
    processed_img = preprocess_image(file)
    
    # Dự đoán
    prediction = model.predict(processed_img)
    # Giả sử class 0 là Normal, class 1 là Positive
    score = float(prediction[0][0])
    result = "Positive" if score > 0.5 else "Negative"
    confidence = round(score * 100 if score > 0.5 else (1 - score) * 100, 2)
    
    return jsonify({'result': result, 'confidence': confidence})

if __name__ == '__main__':
    app.run(port=5000, debug=True)