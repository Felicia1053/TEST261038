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

import java.sql.ResultSet;
import java.util.ArrayList;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public class Add_User extends AppCompatActivity {
    //
    private EditText etUserID, etUserName, etUserPassword , etClass;
    private Button btnReturn, btnSave;
    private MariaDB db;
    private ExecutorService sqlService;
    //
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        EdgeToEdge.enable(this);
        setContentView(R.layout.activity_user_add);
        ViewCompat.setOnApplyWindowInsetsListener(findViewById(R.id.main), (v, insets) -> {
            Insets systemBars = insets.getInsets(WindowInsetsCompat.Type.systemBars());
            v.setPadding(systemBars.left, systemBars.top, systemBars.right, systemBars.bottom);
            return insets;
        });
        //
        etUserID = findViewById(R.id.etUserID);
        etUserName = findViewById(R.id.etUserName);
        etUserPassword = findViewById(R.id.etUserPassword);
        etClass = findViewById(R.id.etClass);
        btnReturn = findViewById(R.id.btnReturn);
        btnSave = findViewById(R.id.btnAdd);
        //
        sqlService = Executors.newSingleThreadExecutor();
        //
        btnReturn.setOnClickListener(v -> {
            finish();
        });
        //
        Intent receivedIntent = getIntent();
        String intentUserID = receivedIntent.getStringExtra("UserID");

        if (intentUserID != null && !intentUserID.isEmpty()) {
            etUserID.setEnabled(false);

            sqlService.execute(() -> {
                db = new MariaDB();
                try {
                    String sql = "SELECT * FROM user WHERE UserID = ?";
                    ArrayList<String> params = new ArrayList<>();
                    params.add(intentUserID);

                    ResultSet rs = db.sqlQuery(sql, params);
                    if (rs != null && rs.next()) {
                        String uID = rs.getString("UserID");
                        String uClass = rs.getString("Class");
                        String uName = rs.getString("UserName");
                        String uPassword = rs.getString("Password");

                        runOnUiThread(() -> {
                            etUserID.setText(uID);
                            etClass.setText(uClass);
                            etUserName.setText(uName);
                            etUserPassword.setText(uPassword);
                        });
                    }
                } catch (Exception e) {
                    e.printStackTrace();
                    runOnUiThread(() -> Toast.makeText(Add_User.this, "撈取使用者資料失敗", Toast.LENGTH_SHORT).show());
                } finally {
                    if (db != null) db.disconnectDB();
                }
            });
        }

        btnSave.setOnClickListener(v -> {
            String UserID = etUserID.getText().toString().trim();
            String Class = etClass.getText().toString().trim();
            String UserName = etUserName.getText().toString().trim();
            String Password = etUserPassword.getText().toString().trim();

            if (UserID.isEmpty() || Class.isEmpty() || UserName.isEmpty() || Password.isEmpty()) {
                Toast.makeText(Add_User.this, "欄位不可留空", Toast.LENGTH_SHORT).show();
                return;
            } else {
                sqlService.execute(() -> {
                    db = new MariaDB();
                    java.sql.ResultSet rs = null;
                    try {
                        String checkSql = "SELECT * FROM user WHERE UserID = ?";
                        ArrayList<String> checkParams = new ArrayList<>();
                        checkParams.add(UserID);
                        rs = db.sqlQuery(checkSql, checkParams);

                        if (rs != null && db.rsSize(rs) == 0) {
                            String sql = "INSERT INTO user (UserID, Class, UserName, Password) VALUES (?, ?, ?, ?)";
                            ArrayList<String> params = new ArrayList<>();
                            params.add(UserID);
                            params.add(Class);
                            params.add(UserName);
                            params.add(Password);
                            db.sqlUpdate(sql, params);

                            runOnUiThread(() -> {
                                if (!isFinishing() && !isDestroyed()) {
                                    Toast.makeText(Add_User.this, "新增成功", Toast.LENGTH_SHORT).show();
                                    Intent intent = new Intent(Add_User.this, User_List.class);
                                    finish();
                                }
                            });
                        } else {
                            String sql = "UPDATE user SET Class = ?, UserName = ?, Password = ? WHERE UserID = ?";
                            ArrayList<String> params = new ArrayList<>();
                            params.add(Class);
                            params.add(UserName);
                            params.add(Password);
                            params.add(UserID);

                            db.sqlUpdate(sql, params);

                            runOnUiThread(() -> {
                                if (!isFinishing() && !isDestroyed()) {
                                    Toast.makeText(Add_User.this, "修改成功", Toast.LENGTH_SHORT).show();
                                    Intent intent = new Intent(Add_User.this, User_List.class);
                                    finish();
                                }
                            });
                        }
                    }  catch (Exception e) {
                        e.printStackTrace();
                        runOnUiThread(() -> {
                            if (!isFinishing() && !isDestroyed()) {
                                Toast.makeText(Add_User.this, "資料庫更新失敗", Toast.LENGTH_SHORT).show();
                            }
                        });
                    } finally {
                        try {
                            if (rs != null) rs.close();
                        } catch (Exception e) {
                            e.printStackTrace();
                        }
                        if (db != null) db.disconnectDB();
                    }
                });
            }
        });
    }
        //
    @Override
    protected void onDestroy() {
        super.onDestroy();
        if (sqlService != null) {
            sqlService.shutdown();
        }
    }
}
