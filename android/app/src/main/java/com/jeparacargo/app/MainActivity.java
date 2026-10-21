package com.jeparacargo.app;

import android.os.Bundle;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsCompat;
import androidx.core.view.WindowInsetsControllerCompat;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        
        // Menggunakan API modern untuk menyembunyikan navigasi bawah (Navigation Bar)
        // namun tetap menampilkan bar notifikasi/status atas (Status Bar)
        WindowInsetsControllerCompat windowInsetsController =
                WindowCompat.getInsetsController(getWindow(), getWindow().getDecorView());
        
        if (windowInsetsController != null) {
            // Atur agar bar navigasi yang disembunyikan hanya muncul sesaat kalau di-swipe dari bawah
            windowInsetsController.setSystemBarsBehavior(
                    WindowInsetsControllerCompat.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE
            );
            
            // Sembunyikan HANYA navigation bar bawah (tombol home/back/recent)
            windowInsetsController.hide(WindowInsetsCompat.Type.navigationBars());
            
            // Pastikan status bar atas (jam, baterai, notifikasi) TETAP TAMPIL
            windowInsetsController.show(WindowInsetsCompat.Type.statusBars());
        }
    }
}

