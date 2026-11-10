# Training Program

The SPA page is served at `/training/program` inside the Training menu. Its
data and write operations use the Sanctum API registered from
`routes/training-program.php`:

| Method | URL | Action |
|---|---|---|
| GET | `/api/training/program?year={year}` | Read annual program grid |
| POST | `/api/training/program` | Create a program |
| PUT | `/api/training/program/{program}` | Update a program |
| DELETE | `/api/training/program/{program}` | Delete a program |
| POST | `/api/training/program/plan/generate` | Generate missing plans |
| POST | `/api/training/program/{program}/plans` | Add a crew to a plan |
| PUT | `/api/training/program/plans/{plan}` | Move a planned crew |
| DELETE | `/api/training/program/plans/{plan}` | Remove a planned crew |
| PUT | `/api/training/program/{program}/actuals/{year}/{month}` | Save actual training |
| DELETE | `/api/training/program/actuals/{actual}` | Delete actual training |

All endpoints require Sanctum authentication and access to the `training`
page. Editing follows that page's edit permission.

Run `php artisan migrate` to create the program tables. The
`TrainingProgramSeeder` is called after training records by
`TrainingPortalSeeder`. Saving actual training can also update participant
last/next-check dates in Training Record.
