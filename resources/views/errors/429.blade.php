{{-- Reached by the rate limits on calculation, sign-in and password recovery. Says to wait. --}}
@include('errors.layout', [
    'code' => '429',
    'title' => 'ส่งคำขอบ่อยเกินไป',
    'message' => 'ระบบจำกัดจำนวนคำขอต่อนาทีเพื่อให้ใช้งานได้ทั่วถึง',
    'hint' => 'รอสักครู่แล้วลองใหม่อีกครั้ง ข้อมูลที่บันทึกไว้แล้วไม่ได้รับผลกระทบ',
])
