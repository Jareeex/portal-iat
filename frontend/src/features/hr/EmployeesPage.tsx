import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { Alert, Loader } from '../../components/common/Feedback';
import { Field } from '../../components/common/Field';
import { Modal } from '../../components/common/Modal';
import { DataTable, type Column } from '../../components/tables/DataTable';
import { Pagination } from '../../components/tables/Pagination';
import { usePermission } from '../../hooks/usePermission';
import { departmentService } from '../../services/department.service';
import { employeeService, type EmployeeProfilePayload } from '../../services/employee.service';
import { errorMessage } from '../../utils/error';
import type { Department, EmployeeProfile } from '../../types';

interface EmployeeForm {
  employee_number: string;
  job_title: string;
  office: string;
  employment_status: string;
  join_date: string;
  end_date: string;
}

const emptyForm: EmployeeForm = {
  employee_number: '',
  job_title: '',
  office: '',
  employment_status: '',
  join_date: '',
  end_date: '',
};

const employmentStatuses = ['Tetap', 'Kontrak', 'Probation', 'Magang', 'Resign'];

export default function EmployeesPage() {
  const { isMaster, canEdit } = usePermission();
  const canEditEmployees = canEdit('hr-employees');
  const [employees, setEmployees] = useState<EmployeeProfile[]>([]);
  const [departments, setDepartments] = useState<Department[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [departmentId, setDepartmentId] = useState<number | ''>('');
  const [employmentStatus, setEmploymentStatus] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [editing, setEditing] = useState<EmployeeProfile | null>(null);
  const [form, setForm] = useState<EmployeeForm>(emptyForm);
  const [formError, setFormError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const result = await employeeService.list({
        page,
        q: search || undefined,
        department_id: departmentId,
        employment_status: employmentStatus || undefined,
      });
      setEmployees(result.data);
      setLastPage(result.last_page);
      setTotal(result.total);
    } catch (loadError) {
      setError(errorMessage(loadError));
    } finally {
      setLoading(false);
    }
  }, [departmentId, employmentStatus, page, search]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    void departmentService.list().then(setDepartments).catch((loadError) => {
      setError(errorMessage(loadError));
    });
  }, []);

  function openEdit(employee: EmployeeProfile) {
    setEditing(employee);
    setForm({
      employee_number: employee.employee_number ?? '',
      job_title: employee.job_title ?? '',
      office: employee.office ?? '',
      employment_status: employee.employment_status ?? '',
      join_date: employee.join_date ?? '',
      end_date: employee.end_date ?? '',
    });
    setFormError(null);
  }

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!editing) return;

    setSaving(true);
    setFormError(null);
    const payload: EmployeeProfilePayload = {
      employee_number: form.employee_number.trim() || null,
      job_title: form.job_title.trim() || null,
      office: form.office.trim() || null,
      employment_status: form.employment_status || null,
      join_date: form.join_date || null,
      end_date: form.end_date || null,
    };

    try {
      await employeeService.update(editing.id, payload);
      setEditing(null);
      await load();
    } catch (saveError) {
      setFormError(errorMessage(saveError));
    } finally {
      setSaving(false);
    }
  }

  const columns: Column<EmployeeProfile>[] = [
    {
      header: 'Karyawan / akun',
      render: (employee) => (
        <div>
          <strong>{employee.user.name}</strong>
          <div className="muted">{employee.user.email}</div>
        </div>
      ),
    },
    {
      header: 'No. pegawai',
      render: (employee) => employee.employee_number || <span className="muted">Belum diisi</span>,
    },
    {
      header: 'Jabatan',
      render: (employee) => employee.job_title || <span className="muted">Belum diisi</span>,
    },
    {
      header: 'Departemen',
      render: (employee) => employee.user.department?.name ?? <span className="muted">-</span>,
    },
    {
      header: 'Akun',
      render: (employee) => (
        <div>
          <span className={employee.user.role === 'master' ? 'badge badge-accent' : 'badge'}>
            {employee.user.role}
          </span>{' '}
          <span className={employee.user.is_active ? 'badge badge-ok' : 'badge badge-off'}>
            {employee.user.is_active ? 'Aktif' : 'Nonaktif'}
          </span>
        </div>
      ),
    },
    {
      header: 'Status kerja',
      render: (employee) => employee.employment_status || <span className="muted">Belum diisi</span>,
    },
    ...(canEditEmployees
      ? [{
          header: '',
          width: '100px',
          render: (employee: EmployeeProfile) => (
            <div className="row-actions">
              <button type="button" className="btn btn-ghost" onClick={() => openEdit(employee)}>
                Edit
              </button>
            </div>
          ),
        }]
      : []),
  ];

  return (
    <div>
      <div className="page-head page-head-row">
        <div>
          <h2>Employees</h2>
          <p className="muted">Satu akun User = satu profil karyawan, termasuk akun Master. Total: {total}</p>
        </div>
        {isMaster && <Link className="btn btn-primary" to="/master/users">Kelola akun</Link>}
      </div>

      <form
        className="toolbar"
        onSubmit={(event) => {
          event.preventDefault();
          setPage(1);
          setSearch(searchInput.trim());
        }}
      >
        <input
          type="search"
          placeholder="Cari nama, email, no. pegawai, jabatan"
          value={searchInput}
          onChange={(event) => setSearchInput(event.target.value)}
        />
        <select
          value={departmentId}
          onChange={(event) => {
            setPage(1);
            setDepartmentId(event.target.value === '' ? '' : Number(event.target.value));
          }}
        >
          <option value="">Semua departemen</option>
          {departments.map((department) => (
            <option key={department.id} value={department.id}>{department.name}</option>
          ))}
        </select>
        <select
          value={employmentStatus}
          onChange={(event) => {
            setPage(1);
            setEmploymentStatus(event.target.value);
          }}
        >
          <option value="">Semua status kerja</option>
          {employmentStatuses.map((status) => <option key={status} value={status}>{status}</option>)}
        </select>
        <button type="submit" className="btn">Cari</button>
      </form>

      {error && <Alert>{error}</Alert>}
      {loading ? (
        <Loader />
      ) : (
        <>
          <DataTable columns={columns} rows={employees} rowKey={(employee) => employee.id} empty="Belum ada karyawan." />
          <Pagination page={page} lastPage={lastPage} total={total} onChange={setPage} />
        </>
      )}

      {editing && (
        <Modal title={`Profil karyawan · ${editing.user.name}`} onClose={() => setEditing(null)}>
          <form onSubmit={onSubmit} className="form">
            {formError && <Alert>{formError}</Alert>}
            <p className="muted">Nama, email, role, dan departemen akun dikelola di Master Panel → Users.</p>
            <Field label="Nomor pegawai">
              <input value={form.employee_number} onChange={(event) => setForm({ ...form, employee_number: event.target.value })} />
            </Field>
            <Field label="Jabatan">
              <input value={form.job_title} onChange={(event) => setForm({ ...form, job_title: event.target.value })} />
            </Field>
            <Field label="Kantor">
              <input value={form.office} onChange={(event) => setForm({ ...form, office: event.target.value })} />
            </Field>
            <Field label="Status kerja">
              <select value={form.employment_status} onChange={(event) => setForm({ ...form, employment_status: event.target.value })}>
                <option value="">Belum ditentukan</option>
                {employmentStatuses.map((status) => <option key={status} value={status}>{status}</option>)}
              </select>
            </Field>
            <div className="form-row">
              <Field label="Tanggal masuk">
                <input type="date" value={form.join_date} onChange={(event) => setForm({ ...form, join_date: event.target.value })} />
              </Field>
              <Field label="Tanggal akhir">
                <input type="date" value={form.end_date} onChange={(event) => setForm({ ...form, end_date: event.target.value })} />
              </Field>
            </div>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setEditing(null)}>Batal</button>
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Menyimpan...' : 'Simpan profil'}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
