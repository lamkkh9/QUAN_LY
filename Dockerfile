FROM php:8.2-apache

# Sao chép toàn bộ mã nguồn PHP hiện tại vào thư mục chạy của Apache
COPY . /var/www/html/

# Cấp quyền ghi dữ liệu file JSON cho server Apache trên Linux
RUN chown -R www-data:www-data /var/www/html

# Mở cổng mạng 80 để tiếp nhận truy cập từ Internet
EXPOSE 80
