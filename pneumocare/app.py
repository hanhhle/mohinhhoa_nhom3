from flask import Flask, request, jsonify
from flask_cors import CORS
import random
import time

app = Flask(__name__)
CORS(app)

@app.route('/predict', methods=['POST'])
def predict():
    if 'file' not in request.files:
        return jsonify({'error': 'No file uploaded'}), 400
        
    # 1. Giả lập AI đang "đọc" ảnh mất 2 giây
    time.sleep(2)
    
    # 2. Random kết quả chẩn đoán
    confidence = random.uniform(0.85, 0.99)
    is_positive = random.choice([True, False])
    
    result_label = "Positive" if is_positive else "Negative"
    conf_percent = round(confidence * 100, 1)

    return jsonify({
        'result': result_label,
        'confidence': conf_percent
    })

if __name__ == '__main__':
    print("🚀 AI Server giả lập đang chạy tại http://127.0.0.1:5000")
    app.run(port=5000, debug=True)