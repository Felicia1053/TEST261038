package nuu.im.practice1140216;

import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Bundle;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.Toast;

import androidx.activity.EdgeToEdge;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowInsetsCompat;

import java.util.ArrayList;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public class Login extends AppCompatActivity {
    //
    private Button btnRegister, btnLogin;
    private EditText etUserAccount, etUserPassword;
    private MariaDB db;
    private ExecutorService sqlService;
    //
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        EdgeToEdge.enable(this);
        setContentView(R.layout.activity_login);
        ViewCompat.setOnApplyWindowInsetsListener(findViewById(R.id.main), (v, insets) -> {
            Insets systemBars = insets.getInsets(WindowInsetsCompat.Type.systemBars());
            v.setPadding(systemBars.left, systemBars.top, systemBars.right, systemBars.bottom);
            return insets;
        });
        //
        btnRegister = findViewById(R.id.btnRegister);
        btnLogin = findViewById(R.id.btnLogin);
        etUserAccount = findViewById(R.id.etUserAccount);
        etUserPassword = findViewById(R.id.etUserPassword);
        //
        sqlService = Executors.newSingleThreadExecutor();
        //
        btnRegister.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                Intent intent = new Intent(Login.this, Register.class);
                startActivity(intent);
            }
        });

        btnLogin.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                String account = etUserAccount.getText().toString();
                String password = etUserPassword.getText().toString();

                if (account.isEmpty() || password.isEmpty()) {
                    Toast.makeText(Login.this, "帳號或密碼不可為空", Toast.LENGTH_SHORT).show();
                    return;
                } else {
                    sqlService.execute(() -> {
                        db = new MariaDB();

                        String sql = "SELECT * FROM user WHERE UserID = ? AND Password = ?";
                        ArrayList<String> params = new ArrayList<>();
                        params.add(account);
                        params.add(password);

                        java.sql.ResultSet rs = db.sqlQuery(sql, params);

                        try {
                            if (db.rsSize(rs) == 1) {
                                rs.next();

                                String userName = rs.getString("UserName");

                                runOnUiThread(() -> {
                                    Toast.makeText(Login.this, "登入成功 " + userName, Toast.LENGTH_SHORT).show();

                                    SharedPreferences pref = getSharedPreferences("UserSession", MODE_PRIVATE); ////
                                    pref.edit().putString("USER_ID", account).commit(); ////

                                    Intent intent = new Intent(Login.this, Course_Search.class);
                                    startActivity(intent);
                                    finish();
                                });
                            } else {
                                runOnUiThread(() -> Toast.makeText(Login.this, "帳密錯誤", Toast.LENGTH_SHORT).show());
                            }
                        } catch (java.sql.SQLException e) {
                            e.printStackTrace();
                            runOnUiThread(() -> Toast.makeText(Login.this, "資料庫讀取失敗", Toast.LENGTH_SHORT).show());
                        } finally {
                            if (db != null) db.disconnectDB();
                        }
                    });
                }
            }
        });
    }
    @Override
    protected void onDestroy() {
        super.onDestroy();
        if (sqlService != null) {
            sqlService.shutdown();
        }
    }
}
