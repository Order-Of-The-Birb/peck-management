import discord, logging
from datetime import timedelta, datetime, UTC
from dateutil.relativedelta import relativedelta
from discord.ext import commands
from typing import TYPE_CHECKING
if __name__ == "__main__":
	from os import path
	from sys import path as sys_path
	sys_path.append(path.abspath(path.join(path.dirname(__file__), '..')))
if TYPE_CHECKING:
	from utils.bot import Bot
from utils.bot import debug_only
import utils.generic as genericUtil
import utils.time as timeUtil
import utils.wt as wtUtil
__reload_deps__ = ("utils.generic", "utils.time", "utils.wt")
old_timer_interval = relativedelta(years=1)

class MemberCommands(commands.Cog):
	def __init__(self, bot:'Bot'):
		self.bot = bot
		self.logger = logging.getLogger(__name__)
		self.logger.setLevel(bot.logLevel)
		self.logger.debug("Member Commands initialized")
	async def interaction_check(self, interaction:discord.Interaction):
		if isinstance(interaction.channel, discord.DMChannel):
			await interaction.response.send_message("You need to execute this command in a server (Required to check for permissions)")
			return False
		elif self.bot.get_guild(self.bot.peckServer).get_role(self.bot.memberRoleID) in interaction.user.roles: 
			return True
		await interaction.response.send_message("You don't have the required Permissions", ephemeral=True, delete_after=5)
		return False
	announcement_delay = 30
	last_sqb_announcement = datetime.now(UTC)- timedelta(minutes=announcement_delay) 
	announcements = 0
	use_sqb_ping_role:bool = False
	# region Sqb commands
	group_sqb = discord.app_commands.Group(name="sqb", description="Commands relating to announcing SQB")

	@group_sqb.command(name="announce")
	@discord.app_commands.guild_only()
	async def announce_sqb(self, interaction:discord.Interaction):
		'''This command is to be used to announce SQB. Usable by squadron members'''
		await interaction.response.defer(ephemeral=True)
		current_time = datetime.now(UTC)
		start_time, end_time = timeUtil.get_sqb_timebracket()
		voice_channel = interaction.user.voice.channel if interaction.user.voice else None
		if not (self.last_sqb_announcement <= (datetime.now(UTC)- timedelta(minutes=self.announcement_delay))) and not self.bot.debug:
			await interaction.edit_original_response(content=f"Error: Last SQB ping was less than {self.announcement_delay} minutes ago.")
			return
		if not (start_time <= current_time <= end_time) and not self.bot.debug:
			await interaction.edit_original_response(content="Error: Outside of SQB time frame.")
			return
		if not (voice_channel is not None and voice_channel.id == self.bot.sqbChID) and not self.bot.debug:
			await interaction.edit_original_response(content=f"You have to be in <#{self.bot.sqbChID}> to announce SQB.")
			return
		await interaction.edit_original_response(content="Announcing SQB...")
		channel = self.bot.get_channel(self.bot.announcementsChID)
		if self.use_sqb_ping_role:
			self.announcements += 1
			role_id = 1065769588849115248
			if self.announcements >= 3:
				role_id = 919803983286128661
		else:
			role_id = 919803983286128661
		await channel.send(f"<@&{role_id}> an SQB ping was requested by {interaction.user.mention}. You can join <#{self.bot.sqbChID}> to play SQB.\nCurrent BR:\n# {wtUtil.sqb_br(True)[0]}\n\tTimeframe ends {timeUtil.discord_timestamp(timeUtil.toUnix(timeUtil.get_sqb_timebracket()[1]), "R")}\n\n{await genericUtil.random_propaganda(self.bot)}")
		self.last_sqb_announcement = datetime.now(UTC)
		await interaction.edit_original_response(content="SQB announced.")
		embed = discord.Embed(title="SQB Announcement", color=0x0000FF, timestamp=datetime.now(UTC), description="A member just announced SQB")
		embed.add_field(name="Username", value=f"{interaction.user.name}")
		embed.add_field(name="User ID", value=f"{interaction.user.id}")
		embed.add_field(name="User mention", value=interaction.user.mention)
		await (self.bot.get_channel(self.bot.spamChID).send(embed=embed))

	@group_sqb.command(name="plan")
	@discord.app_commands.guild_only()
	async def plan_sqb(self, interaction:discord.Interaction, time:discord.app_commands.Timestamp):
		await interaction.response.defer(ephemeral=True)
		if self.bot.planSQB.announcementMessage is not None and not self.bot.debug:
			await interaction.edit_original_response(content=f"A member has already started planning for SQB.\nPlease head to the [post]({self.bot.planSQB.announcementMessage.jump_url}) and apply to be notified instead.")
			return
		channel = self.bot.get_channel(self.bot.announcementsChID)
		current_br = wtUtil.sqb_br(True)[0]
		self.bot.planSQB.timeframe = timeUtil.get_sqb_timebracket()
		embed = discord.Embed(title="SQB Plan", color=0xFF0000, description="A member has planned to play SQB today. You can apply to be notified once the session starts")
		embed.set_author(name=f"Posted by {interaction.user.name}", icon_url=interaction.user.display_avatar.url)
		embed.add_field(name="Starting", value=timeUtil.discord_timestamp(self.bot.planSQB.timeframe[0], "R"), inline=True)
		embed.add_field(name="Battle Rating", value=f"{current_br}", inline=True)
		embed.add_field(name="Ending", value=timeUtil.discord_timestamp(self.bot.planSQB.timeframe[1], "R"), inline=True)
		embed.add_field(name="Current applicants", value=f"{len(self.bot.planSQB.applicants)+1} / 8", inline=False)
		embed.set_image(url=(await genericUtil.random_propaganda(self.bot)))
		embed.set_footer(text=f"Provided by PECK bot", icon_url=self.bot.iconURL)
		async def apply(interaction:discord.Interaction):
			if self.bot.get_guild(self.bot.peckServer).get_role(self.bot.memberRoleID) not in interaction.user.roles:
				await interaction.followup.send(content="You are not a member, you can not apply to join SQB.", ephemeral=True, silent=True)
			elif interaction.user.id not in self.bot.planSQB.applicants: 
				self.bot.planSQB.applicants.append(interaction.user.id)
				await interaction.followup.send(content="**You have been added to the applicants list! You will be notified by DM (or Pinged if DMs aren't accessible) when the session starts.**", ephemeral=True, silent=True)
				tmp = self.bot.planSQB.announcementMessage.embeds[0].set_field_at(
					index=3,
					name="Current applicants",
					value=f"{len(self.bot.planSQB.applicants)} / 8",
					inline=False
				)
				await self.bot.planSQB.announcementMessage.edit(embed=tmp)
			else:
				await interaction.followup.send(content="You have already applied before. *You will be notified once the session starts.*", ephemeral=True, silent=True)
		async def cancel(interaction:discord.Interaction):
			try:
				if interaction.user.id in self.bot.planSQB.applicants:
					self.bot.planSQB.applicants.remove(interaction.user.id)
					await interaction.followup.send(content="You have successfully been *removed* from the applicants list. **You will not be sent a DM when the session starts.**", ephemeral=True, silent=True)
					if len(self.bot.planSQB.applicants) < 1:
						await interaction.message.edit(content="All applicants have cancelled their application, so this plan has been cancelled.", embed=None, view=None)
						self.bot.planSQB = self.bot._planSQB()
					else:
						tmp = self.bot.planSQB.announcementMessage.embeds[0].set_field_at(
							index=3,
							name="Current applicants",
							value=f"{len(self.bot.planSQB.applicants)} / 8",
							inline=False
						)
						await self.bot.planSQB.announcementMessage.edit(embed=tmp)
				else:
					await interaction.followup.send(content="You haven't applied to the planned SQB session, *nothing has changed.*", ephemeral=True, silent=True)
			except:
				self.logger.exception("An error occurred while handling SQB plan application cancellation")
		self.bot.planSQB.announcementMessage = await channel.send(content=interaction.guild.get_role(self.bot.pingRoleID).mention, embed=embed, view=genericUtil.genericButtons(acceptFunc=apply, denyFunc=cancel, timeout=None, acceptLabel="Apply", denyLabel="Cancel Application"))
		if interaction.user.id not in self.bot.planSQB.applicants:
			self.bot.planSQB.applicants.append(interaction.user.id)
		embed = discord.Embed(title="SQB Plan", color=0x0000FF, timestamp=datetime.now(UTC), description="A member just called for SQB to be planned")
		embed.add_field(name="Username", value=f"{interaction.user.name}")
		embed.add_field(name="User ID", value=f"{interaction.user.id}")
		embed.add_field(name="User mention", value=interaction.user.mention)
		await (self.bot.get_channel(self.bot.spamChID).send(embed=embed))
		await interaction.edit_original_response(content=f"The SQB plan has been posted in announcements and you have been automatically added as an applicant. When you are ready to call for it, join <#{self.bot.sqbChID}>.\n-# if you join before the said timebracket the bot will check every ~30 seconds and announce once the timebracket starts")
	# endregion
	# region Get Role commands
	group_get_roles = discord.app_commands.Group(name="get_role", description="For getting member-specific roles")

	@group_get_roles.command(name="old_timer")
	@discord.app_commands.guild_only()
	async def get_old_timer(self, interaction:discord.Interaction):
		"""This command checks your saved join date and gives you the old timer role depending if you have been in the squadron for long enough"""
		await interaction.response.defer(thinking=True)
		main_profile = self.bot.db.getByDID(interaction.user.id)
		if not main_profile:
			await interaction.edit_original_response(content="You aren't registered in the squadron member list.")
			return
		main_profile = min(main_profile, key=lambda u: (u.joindate is None, u.joindate))
		x_time_ago = (datetime.now(UTC) - old_timer_interval).date()
		old_timer = interaction.guild.get_role(self.bot.oldTimerRoleID)
		if main_profile.joindate is None:
			await interaction.edit_original_response(content=f"You seem to not have a join date saved in the database. Please contact <@{self.bot.yoshinoID}> about this.")
		elif interaction.user.get_role(old_timer.id) is not None:
			await interaction.edit_original_response(content="You already seem to have the role.")
		elif main_profile.joindate > x_time_ago:
			await interaction.edit_original_response(content="You haven't been in the squadron for a year yet!")
		elif main_profile.joindate <= x_time_ago:
			await interaction.user.add_roles(old_timer, reason="User is an old timer")
			await interaction.edit_original_response(content="Role given.")
			embed = discord.Embed(title="Old timer role promotion")
			embed.add_field(name="Discord user", value=interaction.user.mention)
			tmp = self.bot.get_channel(self.bot.spamChID)
			await tmp.send(embed=embed)

	@group_get_roles.command(name="sqb_ping")
	@discord.app_commands.guild_only()
	async def get_ping_role(self, interaction:discord.Interaction):
		await interaction.response.defer(thinking=True, ephemeral=True)
		pingrole = interaction.guild.get_role(self.bot.pingRoleID)
		if pingrole in interaction.user.roles:
			await interaction.edit_original_response(content=f"You already have the role. If you wish to remove it use the `/remove_ping_role` command!")
			return
		await interaction.user.add_roles(pingrole)
		await interaction.edit_original_response(content=f"You have been given the {pingrole.mention} role.")
	# endregion
	# region Remove Role commands
	group_rm_roles = discord.app_commands.Group(name="remove_role", description="For removing member-specific roles")

	@group_rm_roles.command(name="sqb_ping")
	@discord.app_commands.guild_only()
	async def rm_ping_role(self, interaction:discord.Interaction):
		await interaction.response.defer(thinking=True, ephemeral=True)
		pingrole = interaction.guild.get_role(self.bot.pingRoleID)
		if pingrole not in interaction.user.roles:
			await interaction.edit_original_response(content=f"You don't have the role. If you wish to get it use the `/get_ping_role` command!")
			return
		await interaction.user.remove_roles(pingrole)
		await interaction.edit_original_response(content=f"Your {pingrole.mention} role has been removed.")
	# endregion

async def setup(bot: 'Bot'):
	await bot.add_cog(MemberCommands(bot))