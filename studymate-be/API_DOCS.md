# API Docs Ringkas

## Health Check
`GET /api/health`

## Auth
### Login
`POST /api/auth/login`

Body:
```json
{
  "email": "raffa@kampus.ac.id",
  "password": "123456"
}
```

### Register
`POST /api/auth/register`

Body:
```json
{
  "name": "Nama Mahasiswa",
  "email": "nama@kampus.ac.id",
  "password": "123456",
  "studentId": "1030123xxxx",
  "programId": "prog-informatika"
}
```

## Profile
### Get User
`GET /api/users/:id`

### Update User
`PUT /api/users/:id`

## Dashboard
`GET /api/dashboard/:userId`

## Groups
### List Groups
`GET /api/groups?search=&courseId=`

### Create Group
`POST /api/groups`

### Update Group
`PUT /api/groups/:id`

### Delete Group
`DELETE /api/groups/:id`

### Join Group
`POST /api/groups/:id/join`

## Matchmaking
`GET /api/matchmaking/:userId`

## Admin
### Summary
`GET /api/admin/summary`

### Programs
- `POST /api/admin/programs`
- `PUT /api/admin/programs/:id`
- `DELETE /api/admin/programs/:id`

### Courses
- `POST /api/admin/courses`
- `PUT /api/admin/courses/:id`
- `DELETE /api/admin/courses/:id`

### Locations
- `POST /api/admin/locations`
- `PUT /api/admin/locations/:id`
- `DELETE /api/admin/locations/:id`

### Users
- `PUT /api/admin/users/:id`
