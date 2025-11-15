from flask import Flask, jsonify, request
from flask_cors import CORS
import requests
import json

app = Flask(__name__)
CORS(app)

FINNHUB_API_KEY = 'd46ntu1r01qgc9etnfngd46ntu1r01qgc9etnfo0'
FINNHUB_BASE_URL = 'https://finnhub.io/api/v1'

@app.route('/api/quote/<ticker>', methods=['GET'])
def get_quote(ticker):
    """ดึงข้อมูลราคา"""
    try:
        url = f'{FINNHUB_BASE_URL}/quote?symbol={ticker.upper()}&token={FINNHUB_API_KEY}'
        response = requests.get(url, timeout=10)
        response.raise_for_status()
        return jsonify(response.json())
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/company/<ticker>', methods=['GET'])
def get_company(ticker):
    """ดึงข้อมูลบริษัท"""
    try:
        url = f'{FINNHUB_BASE_URL}/company-profile2?symbol={ticker.upper()}&token={FINNHUB_API_KEY}'
        response = requests.get(url, timeout=10)
        response.raise_for_status()
        return jsonify(response.json())
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/metrics/<ticker>', methods=['GET'])
def get_metrics(ticker):
    """ดึงตัวชี้วัดทางการเงิน"""
    try:
        url = f'{FINNHUB_BASE_URL}/stock/metric?symbol={ticker.upper()}&metric=all&token={FINNHUB_API_KEY}'
        response = requests.get(url, timeout=10)
        response.raise_for_status()
        return jsonify(response.json())
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/peers/<ticker>', methods=['GET'])
def get_peers(ticker):
    """ดึงข้อมูลบริษัทคู่แข่ง"""
    try:
        url = f'{FINNHUB_BASE_URL}/stock/peers?symbol={ticker.upper()}&token={FINNHUB_API_KEY}'
        response = requests.get(url, timeout=10)
        response.raise_for_status()
        return jsonify(response.json())
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/recommendation/<ticker>', methods=['GET'])
def get_recommendation(ticker):
    """ดึงคำแนะนำจากนักวิเคราะห์"""
    try:
        url = f'{FINNHUB_BASE_URL}/stock/recommendation?symbol={ticker.upper()}&token={FINNHUB_API_KEY}'
        response = requests.get(url, timeout=10)
        response.raise_for_status()
        return jsonify(response.json())
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/health', methods=['GET'])
def health_check():
    """ตรวจสอบสถานะของ API"""
    return jsonify({'status': 'ok', 'message': 'Stock Analyzer Backend Running ✅'})

if __name__ == '__main__':
    print("""
    ╔══════════════════════════════════════════════════╗
    ║   Stock Analyzer Backend - API Proxy Server      ║
    ║   🚀 กำลังทำงาน http://localhost:5000            ║
    ║   ✅ ถ้าเห็นข้อความนี้ให้เปิดไฟล์ HTML ที่สร้างขึ้น ║
    ╚══════════════════════════════════════════════════╝
    """)
    app.run(debug=True, host='127.0.0.1', port=5000)
