{{-- The session expired between loading a form and submitting it. Reloading is the whole fix. --}}
@include('errors.layout', [
    'code' => '419',
    'title' => 'หน้านี้หมดอายุแล้ว',
    'message' => 'คุณเปิดหน้านี้ทิ้งไว้นานเกินไป ระบบจึงไม่รับข้อมูลที่ส่งมาเพื่อความปลอดภัย',
    'hint' => 'โหลดหน้าเดิมใหม่แล้วทำรายการอีกครั้ง ข้อมูลที่กรอกไว้ในหน้านั้นจะต้องกรอกใหม่',
])
