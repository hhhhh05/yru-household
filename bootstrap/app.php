<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /* กำหนดปลายทางให้ชัด ไม่พึ่งค่าปริยายของเฟรมเวิร์ก
           — ยังไม่ล็อกอินแล้วเปิดหน้าที่ต้องล็อกอิน → ส่งไปหน้าล็อกอิน
           — ล็อกอินอยู่แล้วแต่เปิดหน้าล็อกอิน → ส่งกลับหน้าภาพรวม
           (ค่าปริยายของ Laravel ชี้ไป /dashboard ซึ่งโปรเจกต์นี้ไม่มี จะกลายเป็น 404) */
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
