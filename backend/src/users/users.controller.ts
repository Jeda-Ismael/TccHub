import { Body, Controller, Delete, ForbiddenException, Get, Param, Patch, Post, Query, UseGuards } from '@nestjs/common';
import { Role } from '@prisma/client';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { RolesGuard } from '../common/guards/roles.guard';
import { Roles } from '../common/decorators/roles.decorator';
import { CurrentUser } from '../common/decorators/current-user.decorator';
import { UsersService } from './users.service';
import { UpdateRoleDto } from './dto/update-role.dto';
import { CreateUserDto } from './dto/create-user.dto';

@Controller('users')
@UseGuards(JwtAuthGuard, RolesGuard)
export class UsersController {
  constructor(private usersService: UsersService) {}

  // Qualquer usuário autenticado pode listar por papel (ex: aluno escolhendo
  // orientador, coordenação escolhendo avaliadores). Só a Coordenação pode
  // listar todos os usuários sem filtro.
  @Get()
  findAll(@Query('role') role: Role | undefined, @CurrentUser() user: any) {
    if (!role && user.role !== Role.COORDINATION) {
      throw new ForbiddenException('Informe um papel para filtrar a busca');
    }
    return this.usersService.findAll(role);
  }

  @Post()
  @Roles(Role.COORDINATION)
  create(@Body() dto: CreateUserDto) {
    return this.usersService.create(dto);
  }

  @Patch(':id/role')
  @Roles(Role.COORDINATION)
  updateRole(@Param('id') id: string, @Body() dto: UpdateRoleDto) {
    return this.usersService.updateRole(id, dto.role);
  }

  @Delete(':id')
  @Roles(Role.COORDINATION)
  remove(@Param('id') id: string, @CurrentUser() user: any) {
    return this.usersService.remove(id, user.id);
  }
}
