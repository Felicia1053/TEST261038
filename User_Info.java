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

public class User_Info extends AppCompatActivity {
    //
    private EditText etUserID, etUserName, etUserPassword;
    private Button btnReturn, btnAdd,btnCourse, btnUser, btnLogout;
    private ArrayList<String> courseList = new ArrayList<>();
    private MariaDB db;
    private ExecutorService sqlService;
    private String currentStudentID;
    //
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        EdgeToEdge.enable(this);
        setContentView(R.layout.activity_user_info_edit);
        ViewCompat.setOnApplyWindowInsetsListener(findViewById(R.id.main), (v, insets) -> {
            Insets systemBars = insets.getInsets(WindowInsetsCompat.Type.systemBars());
            v.setPadding(systemBars.left, systemBars.top, systemBars.right, systemBars.bottom);
            return insets;
        });
        //
        etUserID = findViewById(R.id.etUserID);
        etUserName = findViewById(R.id.etUserName);
        etUserPassword = findViewById(R.id.etUserPassword);
        btnReturn = findViewById(R.id.btnReturn);
        btnAdd = findViewById(R.id.btnAdd);
        btnLogout = findViewById(R.id.btnLogout);
        btnCourse = findViewById(R.id.btnCourse);
        btnUser = findViewById(R.id.btnUser);
        //
        sqlService = Executors.newSingleThreadExecutor();
        //
        SharedPreferences pref = getSharedPreferences("UserSession", MODE_PRIVATE);
        currentStudentID = pref.getString("USER_ID", "");
        //
        sqlService.execute(() -> {
            db = new MariaDB();
            try {
                String sql = "SELECT * FROM user WHERE UserID = ?";
                ArrayList<String> params = new ArrayList<>();
                params.add(currentStudentID);

                android.util.Log.d("DEBUG_SQL", "查詢學號: " + currentStudentID);

                java.sql.ResultSet rs = db.sqlQuery(sql, params);
                if (rs != null && rs.next()) {
                    String name = rs.getString("UserName");
                    String pass = rs.getString("Password");
                    int isAdmin = rs.getInt("Admin");

                    runOnUiThread(() -> {
                        etUserID.setText(currentStudentID);
                        etUserID.setEnabled(false);
                        etUserName.setText(name);
                        etUserPassword.setText(pass);

                        if (isAdmin == 1) {
                            btnCourse.setEnabled(true);
                            btnUser.setEnabled(true);
                        } else {
                            btnCourse.setEnabled(false);
                            btnUser.setEnabled(false);
                        }
                    });
                }
            }catch (Exception e) {
                e.printStackTrace();
            } finally {
                if (db != null) db.disconnectDB();
            }
        });
        //
        btnReturn.setOnClickListener(v -> {
            finish();
        });
        //
        btnLogout.setOnClickListener(v -> {
            SharedPreferences.Editor editor = pref.edit();
            editor.remove("USER_ID");
            editor.apply();
            Toast.makeText(User_Info.this, "已登出", Toast.LENGTH_SHORT).show();

            Intent intent = new Intent(User_Info.this, Login.class);
            intent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TASK);// 強制將後台堆疊裡的所有選課相關畫面（Course_Search 等）全數關閉
            startActivity(intent);
            finish();
        });
        //
        btnUser.setOnClickListener(v -> {
            Intent intent = new Intent(User_Info.this, User_List.class);
            Toast.makeText(User_Info.this, "前往使用者列表", Toast.LENGTH_SHORT).show();
            startActivity(intent);
        });
        //
        btnCourse.setOnClickListener(v -> {
            Intent intent = new Intent(User_Info.this, Add_Course.class);
            Toast.makeText(User_Info.this, "前往新增課程", Toast.LENGTH_SHORT).show();
            startActivity(intent);
        });
        //
        btnAdd.setOnClickListener(v -> {
            String userID = etUserID.getText().toString().trim();
            String userName = etUserName.getText().toString().trim();
            String userPassword = etUserPassword.getText().toString().trim();
            //
            if (userID.isEmpty() || userName.isEmpty() || userPassword.isEmpty()) {
                Toast.makeText(User_Info.this, "欄位不可留空", Toast.LENGTH_SHORT).show();
                return;
            } else {
                sqlService.execute(() -> {
                    db = new MariaDB();
                    try {
                        String sql = "UPDATE user SET UserName = ?, Password = ? WHERE UserID = ?";
                        ArrayList<String> params = new ArrayList<>();
                        params.add(userName);
                        params.add(userPassword);
                        params.add(userID);
                        db.sqlUpdate(sql, params);
                        runOnUiThread(() -> Toast.makeText(User_Info.this, "更新成功", Toast.LENGTH_SHORT).show());
                    } catch (Exception e) {
                        e.printStackTrace();
                        runOnUiThread(() -> Toast.makeText(User_Info.this, "資料庫更新失敗", Toast.LENGTH_SHORT).show());
                    } finally {
                        if (db != null) db.disconnectDB();
                    }
                });
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
