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

public class Add_Course extends AppCompatActivity {
    //
    private EditText etCourseID, etCourseName, etCourseContent;
    private Button btnReturn, btnAdd;
    private MariaDB db;
    private ExecutorService sqlService;
    //
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        EdgeToEdge.enable(this);
        setContentView(R.layout.activity_add_course);
        ViewCompat.setOnApplyWindowInsetsListener(findViewById(R.id.main), (v, insets) -> {
            Insets systemBars = insets.getInsets(WindowInsetsCompat.Type.systemBars());
            v.setPadding(systemBars.left, systemBars.top, systemBars.right, systemBars.bottom);
            return insets;
        });
        //
        etCourseID = findViewById(R.id.etCourseID);
        etCourseName = findViewById(R.id.etCourseName);
        etCourseContent = findViewById(R.id.etCourseContent);
        btnReturn = findViewById(R.id.btnReturn);
        btnAdd = findViewById(R.id.btnAdd);
        //
        sqlService = Executors.newSingleThreadExecutor();
        //
        btnReturn.setOnClickListener(v -> {
            finish();
        });
        //
        btnAdd.setOnClickListener(v -> {
            String CourseID = etCourseID.getText().toString().trim();
            String CourseName = etCourseName.getText().toString().trim();
            String CourseContent = etCourseContent.getText().toString().trim();
            //
            if (CourseID.isEmpty() || CourseName.isEmpty()) {
                Toast.makeText(Add_Course.this, "欄位不可留空", Toast.LENGTH_SHORT).show();
                return;
            } else {
                sqlService.execute(() -> {
                    db = new MariaDB();
                    try {
                        String sql = "INSERT INTO course (CourseID, CourseName, CourseContent) VALUES (?, ?, ?)";
                        ArrayList<String> params = new ArrayList<>();
                        params.add(CourseID);
                        params.add(CourseName);
                        params.add(CourseContent);
                        db.sqlUpdate(sql, params);
                        runOnUiThread(() -> {
                            Toast.makeText(getApplicationContext(), "新增成功", Toast.LENGTH_SHORT).show();
                            finish();
                        });
                    } catch (Exception e) {
                        e.printStackTrace();
                        runOnUiThread(() -> Toast.makeText(Add_Course.this, "資料庫更新失敗", Toast.LENGTH_SHORT).show());
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
