package nuu.im.practice1140216;

import android.content.Intent;
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

public class Register extends AppCompatActivity {
    //
    private Button btnConfirm,btnReturn;
    private EditText etUserPassword,etUserName,etUserID;
    private MariaDB db;
    private ExecutorService sqlService;
    //
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        EdgeToEdge.enable(this);
        setContentView(R.layout.activity_register);
        ViewCompat.setOnApplyWindowInsetsListener(findViewById(R.id.main), (v, insets) -> {
            Insets systemBars = insets.getInsets(WindowInsetsCompat.Type.systemBars());
            v.setPadding(systemBars.left, systemBars.top, systemBars.right, systemBars.bottom);
            return insets;
        });
        //
        btnConfirm = findViewById(R.id.btnConfirm);
        btnReturn = findViewById(R.id.btnReturn);
        etUserPassword = findViewById(R.id.etUserPassword);
        etUserName = findViewById(R.id.etUserName);
        etUserID = findViewById(R.id.etUserID);
        //
        sqlService = Executors.newSingleThreadExecutor();
        //
        btnConfirm.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                String id = etUserID.getText().toString();
                String name = etUserName.getText().toString();
                String password = etUserPassword.getText().toString();
                //
                if (id.isEmpty() || name.isEmpty() || password.isEmpty()) {
                    Toast.makeText(Register.this, "欄位不可為空", Toast.LENGTH_SHORT).show();
                    return;
                }else {
                    sqlService.execute(() -> {
                        db = new MariaDB();

                        try {
                            String sqlUser = "INSERT INTO user (UserID, UserName, Password) VALUES (?, ?, ?)";
                            ArrayList<String> paramsUser = new ArrayList<>();
                            paramsUser.add(id);
                            paramsUser.add(name);
                            paramsUser.add(password);
                            db.sqlUpdate(sqlUser, paramsUser);

                            runOnUiThread(() -> {
                                Intent intent = new Intent(Register.this, Login.class);
                                startActivity(intent);
                                Toast.makeText(Register.this, "註冊成功", Toast.LENGTH_SHORT).show();
                                finish();
                            });
                        } catch (Exception e) {
                            e.printStackTrace();
                            runOnUiThread(() -> Toast.makeText(Register.this, "註冊失敗", Toast.LENGTH_SHORT).show());
                        } finally {
                            if (db != null) db.disconnectDB();
                        }
                    });
                }
            }
        });

        btnReturn.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                Intent intent = new Intent(Register.this, Login.class);
                startActivity(intent);
                finish();
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
