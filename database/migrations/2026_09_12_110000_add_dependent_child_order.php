<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 07.3 — record the two child facts the printed บุตร allowance depends on.
 *
 * วิธีการกรอกแบบ ภ.ง.ด.90 (docs/tax-source/PND90-2568-filing-instructions.pdf), page 7,
 * ใบแนบ item 3:
 *
 *   3.1 บุตรชอบด้วยกฎหมาย … คนละ 30,000 บาท และสำหรับบุตรชอบด้วยกฎหมายตั้งแต่คนที่สอง
 *       เป็นต้นไปที่เกิดในหรือหลังปี พ.ศ. 2561 ให้หักลดหย่อนได้เพิ่มอีกคนละ 30,000 บาท
 *       โดยในการนับลำดับบุตร ให้นับลำดับของบุตรทุกคนไม่ว่าจะมีชีวิตอยู่หรือไม่ก็ตาม
 *   3.2 บุตรบุญธรรมของผู้มีเงินได้ คนละ 30,000 บาท แต่รวมกันต้องไม่เกินสามคน
 *
 * Two facts therefore cannot be derived from the rows this schema already holds:
 *
 *   child_type   legitimate (ชอบด้วยกฎหมาย) vs adopted (บุญธรรม) — item 3.2's three-child
 *                limit and item 3.3's ordering both turn on it;
 *   birth_order  the child's ลำดับ counted over *every* child of the taxpayer, including
 *                children who are no longer alive and therefore have no dependent row.
 *                It cannot be recovered by sorting the stored rows.
 *
 * Both are nullable: every existing row keeps the result it was calculated under, and a
 * child row without them is reported as unresolved rather than given a guessed order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_return_dependents', function (Blueprint $table): void {
            $table->string('child_type', 20)->nullable()->after('relation_type');
            $table->unsignedSmallInteger('birth_order')->nullable()->after('child_type');
        });
    }

    public function down(): void
    {
        Schema::table('tax_return_dependents', function (Blueprint $table): void {
            $table->dropColumn(['child_type', 'birth_order']);
        });
    }
};
