#!/bin/bash

# --- กรุณาตั้งค่าส่วนนี้ ---
# สำคัญ: อัปเดต path นี้ให้เป็นที่อยู่จริงของโปรเจกต์ของคุณ
SOURCE_DIR="/var/www/html/it-stock/public/uploads"

# ✅ แก้ไข: แยก Share กับ Subdirectory ออกจากกัน
SMB_SERVER_AND_SHARE="//dc01/PR_DB\$"
SMB_SUBDIR="Test/it-stock"

SMB_USER="ch/R_ronachai"
SMB_PASS="Rr24922"
# --- สิ้นสุดการตั้งค่า ---

echo "กำลังเริ่มย้ายไฟล์รูปภาพ..."

if [ ! -d "$SOURCE_DIR" ]; then
  echo "ผิดพลาด: ไม่พบโฟลเดอร์ต้นทาง '$SOURCE_DIR'"
  exit 1
fi

for file_path in "$SOURCE_DIR"/*
do
  if [ -f "$file_path" ]; then
    FILENAME=$(basename "$file_path")
    echo "-> กำลังอัปโหลด: $FILENAME"

    # ✅ แก้ไข: สร้าง command string ใหม่ที่มี 'cd' นำหน้า
    smb_command="cd \"$SMB_SUBDIR\"; put \"$file_path\" \"$FILENAME\""

    # รันคำสั่ง smbclient ด้วย command string ที่แก้ไขแล้ว
    smbclient "$SMB_SERVER_AND_SHARE" -U "$SMB_USER%$SMB_PASS" -c "$smb_command"

    if [ $? -eq 0 ]; then
      echo "   สำเร็จ"
    else
      echo "   !!! ล้มเหลวในการอัปโหลด $FILENAME"
    fi
  fi
done

echo "---"
echo "สคริปต์ย้ายไฟล์ทำงานเสร็จสิ้น"
