{{--
    Deliberately says nothing about the cause. An error page is not a debugging surface, and the
    reader cannot act on a stack trace — only on whether to retry.
--}}
@include('errors.layout', [
    'code' => '500',
    'title' => 'ระบบขัดข้องชั่วคราว',
    'message' => 'เกิดข้อผิดพลาดที่ฝั่งระบบ ไม่ใช่ที่ข้อมูลที่คุณกรอก',
    'hint' => 'ลองใหม่อีกครั้งในอีกสักครู่ หากยังพบปัญหาเดิม กรุณาแจ้งผู้ดูแลระบบ',
])
