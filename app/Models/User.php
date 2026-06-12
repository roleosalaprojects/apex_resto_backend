<?php

namespace App\Models;

use App\Models\CustomerRelations\Customer;
use App\Models\Employees\AttendanceRecord;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeSchedule;
use App\Models\Employees\Role;
use App\Models\Employees\Schedule;
use App\Models\Pos\Sale;
use App\Models\Products\Item;
use App\Traits\SerializesDateToAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SerializesDateToAppTimezone;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'user_id',
        'role_id',
        'schedule_id',
        'deduction',
        'code',
        'rate',
        'customer_id',
        'uniqid',
        'is_customer',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(User::class, 'user_id', 'user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'user_id', 'user_id');
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(Schedule::class, 'id', 'schedule_id');
    }

    public function details(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'user_id', 'user_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class, 'employee_id', 'id');
    }

    public function attendance_records(): HasMany
    {
        return $this->hasMany(Attendance::class, 'user_id', 'user_id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'user_id', 'id');
    }

    public function employeeSchedules(): HasMany
    {
        return $this->hasMany(EmployeeSchedule::class);
    }

    /**
     * Get the schedule for a specific day of week.
     */
    public function getScheduleForDay(int $dayOfWeek): ?EmployeeSchedule
    {
        return $this->employeeSchedules()->forDay($dayOfWeek)->first();
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'user_id', 'user_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }

    public function customer_details(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }
}
