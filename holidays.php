  <?php
  session_start();
  include 'includes/db.php';

  if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
  }

  $toast = $_SESSION['toast'] ?? null;
  unset($_SESSION['toast']);

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $holiday_date = $_POST['holiday_date'];
    $holiday_name = $_POST['holiday_name'];

    if (!empty($_POST['holiday_id'])) {
      $id = (int) $_POST['holiday_id'];
      $stmt = $conn->prepare("UPDATE holidays SET holiday_date = ?, holiday_name = ? WHERE holiday_id = ?");
      $stmt->bind_param("ssi", $holiday_date, $holiday_name, $id);
      $success = $stmt->execute();
      $stmt->close();
      $_SESSION['toast'] = [
        'type' => $success ? 'success' : 'danger',
        'message' => $success ? 'Holiday updated successfully.' : 'Update failed.'
      ];
    } else {
      $stmt = $conn->prepare("INSERT INTO holidays (holiday_date, holiday_name) VALUES (?, ?)");
      $stmt->bind_param("ss", $holiday_date, $holiday_name);
      $success = $stmt->execute();
      $stmt->close();
      $_SESSION['toast'] = [
        'type' => $success ? 'success' : 'danger',
        'message' => $success ? 'Holiday added successfully.' : 'Insert failed.'
      ];
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
  }

  if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $success = $conn->query("DELETE FROM holidays WHERE holiday_id = $id");
    $_SESSION['toast'] = [
      'type' => $success ? 'success' : 'danger',
      'message' => $success ? 'Holiday deleted successfully.' : 'Delete failed.'
    ];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
  }
  include 'includes/header.php';
  ?>

  <!DOCTYPE html>
  <html lang="en">

  <head>
    <meta charset="UTF-8">
    <title>Holiday List</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

    <!-- DataTables Core CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">

    <!-- DataTables Buttons Extension CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables Core JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

    <!-- DataTables Buttons Extension JS -->
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>

    <!-- JSZip (required for Excel export) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <style>
      #holidaysTable thead td {
        background-color: #191c24 !important;
        color: #fff;
      }
    </style>
  </head>

  <body class="d-flex flex-column min-vh-100">

    <!-- Add/Edit Modal -->
    <div class="modal fade" id="addHolidayModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content px-3">
          <form method="POST" id="holidayForm">
            <div class="modal-header">
              <h5 class="modal-title" id="addHolidayModalLabel">Add / Edit Holiday</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="background-color:white !important;"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="holiday_id" id="holiday_id">
              <div class="mb-3">
                <label for="holiday_date" class="form-label">Holiday Date</label>
                <input type="date" class="form-control" name="holiday_date" id="holiday_date" required>
              </div>
              <div class="mb-3">
                <label for="holiday_name" class="form-label">Holiday Name</label>
                <input type="text" class="form-control" name="holiday_name" id="holiday_name" required>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-dark">Save</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Toasts -->
    <div class="position-fixed top-0 end-0 p-3" style="z-index: 1100">
      <div id="toastBox" class="toast align-items-center text-bg-primary border-0" role="alert">
        <div class="d-flex">
          <div class="toast-body" id="toastMsg">Loading...</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>

      <div id="confirmToast" class="toast align-items-center text-bg-warning border-0" role="alert">
        <div class="d-flex justify-content-between">
          <div class="toast-body fw-semibold">Delete this holiday?</div>
          <div class="d-flex align-items-center">
            <a id="confirmDeleteBtn" href="#" class="btn btn-sm btn-light me-2">Yes</a>
            <button type="button" class="btn btn-sm btn-outline-light me-2" data-bs-dismiss="toast">No</button>
          </div>
        </div>
      </div>
    </div>

    <main class="container mt-4 flex-grow-1">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Holiday List</h3>
        <?php if ($_SESSION['role'] === 'admin'): ?>
          <button class="btn btn-dark text-semibold" onclick="openAddModal()">Add Holiday</button>
        <?php endif; ?>
      </div>

      <table id='holidaysTable' class="table text-center table-bordered">
        <thead class="table-dark">
          <tr>
            <th class="text-center">S.No</th>
            <th class="text-center">Date</th>
            <th class="text-center">Holiday Name</th>
            <?php if ($_SESSION['role'] === 'admin'): ?>
              <th class="text-center">Actions</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php
          $i = 1;
          $result = $conn->query("SELECT * FROM holidays ORDER BY holiday_date ASC");
          if ($result->num_rows > 0):
            while ($row = $result->fetch_assoc()):
          ?>
              <tr>
                <td class="bg-transparent"><?= $i++ ?></td>
                <td class="bg-transparent"><?= $row['holiday_date'] ?></td>
                <td class="bg-transparent"><?= $row['holiday_name'] ?></td>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                  <td class="bg-transparent">
                    <button class=" btn btn-sm btn-warning px-3 mx-1" onclick='editHoliday(<?= json_encode($row) ?>)'>Edit</button>
                    <button class="btn btn-sm btn-danger mx-1" onclick="confirmDelete(<?= $row['holiday_id'] ?>)">Delete</button>
                  </td>
                <?php endif; ?>
              </tr>
          <?php
            endwhile;
          else:
            echo "<tr><td colspan='4' class='text-center'>No holidays found.</td></tr>";
          endif;
          ?>
        </tbody>
      </table>

    </main>

    <footer class="text-center mt-auto py-3 text-muted small">
      &copy; <?= date("Y") ?> Employee Leave Portal
    </footer>

    <script>
      function openAddModal() {
        document.getElementById('holidayForm').reset();
        document.getElementById('holiday_id').value = '';
        const modal = new bootstrap.Modal(document.getElementById('addHolidayModal'));
        modal.show();
      }

      function editHoliday(data) {
        document.getElementById('holiday_id').value = data.holiday_id;
        document.getElementById('holiday_date').value = data.holiday_date;
        document.getElementById('holiday_name').value = data.holiday_name;
        const modal = new bootstrap.Modal(document.getElementById('addHolidayModal'));
        modal.show();
      }

      function confirmDelete(id) {
        const toastEl = document.getElementById('confirmToast');
        document.getElementById('confirmDeleteBtn').href = '?delete=' + id;
        new bootstrap.Toast(toastEl).show();
      }

      <?php if ($toast): ?>
        window.addEventListener('DOMContentLoaded', () => {
          const toastBox = document.getElementById('toastBox');
          const toastMsg = document.getElementById('toastMsg');
          toastBox.classList.remove('text-bg-primary', 'text-bg-success', 'text-bg-danger', 'text-bg-warning');
          toastBox.classList.add('text-bg-<?= $toast['type'] ?>');
          toastMsg.textContent = "<?= addslashes($toast['message']) ?>";
          new bootstrap.Toast(toastBox).show();
        });
      <?php endif; ?>
      $(document).ready(function() {
        $('#holidaysTable').DataTable({
          lengthChange: false,
          dom: 'Bfrtip',
          pageLength: 5,
          buttons: [{
            extend: 'excel',
            text: 'Export to Excel'
          }]
        });
      });
    </script>
  </body>

  </html>