package com.jeparacargo.app;

import android.os.Bundle;
import android.view.View;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        
        // Menggunakan API modern untuk menyembunyikan navigasi bawah di tablet/HP baru
        androidx.core.view.WindowInsetsControllerCompat windowInsetsController =
                androidx.core.view.WindowCompat.getInsetsController(getWindow(), getWindow().getDecorView());
        
        // Atur agar bar sistem hanya muncul sesaat kalau di-swipe (Immersive Sticky)
        windowInsetsController.setSystemBarsBehavior(
                androidx.core.view.WindowInsetsControllerCompat.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE
        );
        
        // Sembunyikan semua bar sistem (Fullscreen murni)
        windowInsetsController.hide(androidx.core.view.WindowInsetsCompat.Type.systemBars());
    }
}
